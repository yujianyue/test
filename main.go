package main

import (
	"crypto/rand"
	"encoding/csv"
	"encoding/json"
	"fmt"
	"html/template"
	"log"
	"math/big"
	"net/http"
	"os"
	"path/filepath"
	"sort"
	"strconv"
	"strings"
	"time"
)

// 配置结构体
type Config struct {
	Title   string   `json:"title"`
	Copyr   string   `json:"copyr"`
	Jscss   string   `json:"jscss"`
	Ismas   string   `json:"ismas"`
	Baoha   string   `json:"baoha"`
	Itiao   []string `json:"itiao"`
	Ihide   []string `json:"ihide"`
	Isurl   []string `json:"isurl"`
	Isimg   []string `json:"isimg"`
	Dbdir   string   `json:"dbdir"`
	Dbxls   string   `json:"dbxls"`
	Copyu   string   `json:"copyu"`
	Pagex   int      `json:"pagex"`
	Pagem   int      `json:"pagem"`
}

// 文件信息结构体
type FileInfo struct {
	Name     string   `json:"name"`
	Title    string   `json:"title"`
	Fields   []string `json:"fields"`
	QueryFields []string `json:"queryFields"`
}

// 查询结果结构体
type QueryResult struct {
	Success bool        `json:"success"`
	Message string      `json:"message"`
	Data    [][]string  `json:"data"`
	Total   int         `json:"total"`
	Page    int         `json:"page"`
	Pages   int         `json:"pages"`
	Time    string      `json:"time"`
	Memory  string      `json:"memory"`
}

// 验证码结构体
type Captcha struct {
	Code string `json:"code"`
	Time int64  `json:"time"`
}

var config Config
var captchaMap = make(map[string]Captcha)

// 初始化配置
func initConfig() {
	config = Config{
		Title:   "多输入框都输对精准查询系统(万用查分)",
		Copyr:   "查立得-",
		Jscss:   "V20210909",
		Ismas:   "1",
		Baoha:   "1",
		Itiao:   []string{"姓名", "学号"},
		Ihide:   []string{"密码", "身份证", "身份证号", "身份证号码"},
		Isurl:   []string{"购买地址", "先领优惠券", "领券地址", "网址的列标题"},
		Isimg:   []string{"商品主图", "产品图片"},
		Dbdir:   "./shujuku/",
		Dbxls:   ".xls.php",
		Copyu:   "/",
		Pagex:   10,
		Pagem:   20,
	}
}

// 生成4位数字验证码
func generateCaptcha() string {
	code := ""
	for i := 0; i < 4; i++ {
		n, _ := rand.Int(rand.Reader, big.NewInt(10))
		code += strconv.Itoa(int(n.Int64()))
	}
	return code
}

// 检查验证码
func checkCaptcha(sessionID, inputCode string) bool {
	if captcha, exists := captchaMap[sessionID]; exists {
		if time.Now().Unix()-captcha.Time < 300 { // 5分钟有效期
			return captcha.Code == inputCode
		}
		delete(captchaMap, sessionID)
	}
	return false
}

// 获取文件列表
func getFileList() ([]FileInfo, error) {
	var files []FileInfo
	
	// 确保数据库目录存在
	if err := os.MkdirAll(config.Dbdir, 0755); err != nil {
		return nil, err
	}
	
	// 读取目录下的文件
	entries, err := os.ReadDir(config.Dbdir)
	if err != nil {
		return nil, err
	}
	
	// 过滤符合条件的文件
	var validFiles []os.DirEntry
	for _, entry := range entries {
		if !entry.IsDir() && strings.HasSuffix(entry.Name(), config.Dbxls) {
			validFiles = append(validFiles, entry)
		}
	}
	
	// 按文件名降序排序
	sort.Slice(validFiles, func(i, j int) bool {
		return validFiles[i].Name() > validFiles[j].Name()
	})
	
	// 处理每个文件
	for _, entry := range validFiles {
		filePath := filepath.Join(config.Dbdir, entry.Name())
		file, err := os.Open(filePath)
		if err != nil {
			continue
		}
		defer file.Close()
		
		reader := csv.NewReader(file)
		reader.Comma = '\t' // TSV文件使用制表符分隔
		reader.LazyQuotes = true // 允许懒引号
		reader.FieldsPerRecord = -1 // 不检查字段数量
		
		// 读取前3行
		lines := make([][]string, 0, 3)
		for i := 0; i < 3; i++ {
			line, err := reader.Read()
			if err != nil {
				break
			}
			lines = append(lines, line)
		}
		
		if len(lines) < 3 {
			continue
		}
		
		// 第三行是字段名
		fields := lines[2]
		var queryFields []string
		
		// 检查哪些字段是查询条件
		for _, field := range fields {
			for _, condition := range config.Itiao {
				if field == condition {
					queryFields = append(queryFields, field)
					break
				}
			}
		}
		
		// 只有包含查询条件的文件才添加
		if len(queryFields) > 0 {
			files = append(files, FileInfo{
				Name:        entry.Name(),
				Title:       lines[1][0], // 第二行第一列作为标题
				Fields:      fields,
				QueryFields: queryFields,
			})
		}
	}
	
	return files, nil
}

// 查询数据
func queryData(filename string, conditions map[string]string, page int) (*QueryResult, error) {
	filePath := filepath.Join(config.Dbdir, filename)
	file, err := os.Open(filePath)
	if err != nil {
		return nil, err
	}
	defer file.Close()
	
	reader := csv.NewReader(file)
	reader.Comma = '\t'
	reader.LazyQuotes = true
	reader.FieldsPerRecord = -1
	
	// 读取所有行
	records, err := reader.ReadAll()
	if err != nil {
		return nil, err
	}
	
	if len(records) < 3 {
		return &QueryResult{Success: false, Message: "数据文件格式错误"}, nil
	}
	
	// 获取字段名（第三行）
	fields := records[2]
	
	// 确定查询字段的列索引
	queryFieldIndexes := make(map[string]int)
	for i, field := range fields {
		for _, condition := range config.Itiao {
			if field == condition {
				queryFieldIndexes[field] = i
				break
			}
		}
	}
	
	// 验证所有查询条件都有对应的字段
	for condition := range conditions {
		if _, exists := queryFieldIndexes[condition]; !exists {
			return &QueryResult{Success: false, Message: "查询条件字段不存在: " + condition}, nil
		}
	}
	
	// 查询数据（从第4行开始，前3行是标题）
	var results [][]string
	for i := 3; i < len(records); i++ {
		record := records[i]
		if len(record) <= len(fields) {
			// 检查是否匹配所有查询条件
			match := true
			for condition, value := range conditions {
				if value == "" {
					continue
				}
				fieldIndex := queryFieldIndexes[condition]
				if fieldIndex >= len(record) || record[fieldIndex] != value {
					match = false
					break
				}
			}
			
			if match {
				results = append(results, record)
			}
		}
	}
	
	// 分页处理
	total := len(results)
	pages := (total + config.Pagex - 1) / config.Pagex
	if pages > config.Pagem {
		pages = config.Pagem
	}
	
	if page < 1 {
		page = 1
	}
	if page > pages {
		page = pages
	}
	
	start := (page - 1) * config.Pagex
	end := start + config.Pagex
	if end > total {
		end = total
	}
	
	var pageResults [][]string
	if start < total {
		pageResults = results[start:end]
	}
	
	// 添加字段名作为第一行
	if len(pageResults) > 0 {
		pageResults = append([][]string{fields}, pageResults...)
	}
	
	return &QueryResult{
		Success: true,
		Data:    pageResults,
		Total:   total,
		Page:    page,
		Pages:   pages,
		Time:    time.Now().Format("2006-01-02 15:04:05"),
		Memory:  fmt.Sprintf("%.2f MB", float64(len(results)*len(fields)*8)/1024/1024),
	}, nil
}

// 主页面处理器
func homeHandler(w http.ResponseWriter, r *http.Request) {
	tmpl := `
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{.Title}}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            font-weight: 300;
        }
        
        .form-container {
            padding: 40px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4facfe;
            box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.1);
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .form-col {
            flex: 1;
            min-width: 200px;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .captcha-group {
            display: flex;
            gap: 15px;
            align-items: end;
        }
        
        .captcha-input {
            flex: 1;
        }
        
        .captcha-img {
            width: 120px;
            height: 50px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            cursor: pointer;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        
        .results-container {
            display: none;
            margin-top: 30px;
        }
        
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .view-toggle {
            display: flex;
            gap: 10px;
        }
        
        .view-btn {
            padding: 8px 16px;
            border: 2px solid #4facfe;
            background: white;
            color: #4facfe;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .view-btn.active {
            background: #4facfe;
            color: white;
        }
        
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #e1e5e9;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e1e5e9;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding: 20px;
        }
        
        .page-btn {
            padding: 8px 12px;
            border: 1px solid #ddd;
            background: white;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .page-btn:hover:not(.disabled) {
            background: #4facfe;
            color: white;
            border-color: #4facfe;
        }
        
        .page-btn.disabled {
            color: #ccc;
            cursor: not-allowed;
        }
        
        .page-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
        }
        
        .close-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .close-btn:hover {
            background: #c82333;
        }
        
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #dc3545;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
        }
        
        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 90%;
            max-height: 90%;
            overflow: auto;
        }
        
        .modal-close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        .modal-close:hover {
            color: #333;
        }
        
        .link-text {
            color: #4facfe;
            text-decoration: underline;
            cursor: pointer;
        }
        
        .link-text:hover {
            color: #2c5aa0;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            cursor: pointer;
            border-radius: 8px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            
            .captcha-group {
                flex-direction: column;
            }
            
            .captcha-img {
                width: 100%;
            }
            
            .results-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .pagination {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{.Title}}</h1>
            <p>精准查询系统</p>
        </div>
        
        <div class="form-container">
            <form id="queryForm">
                <div class="form-group">
                    <label for="fileSelect">选择数据文件：</label>
                    <select id="fileSelect" class="form-control" required>
                        <option value="">请选择数据文件...</option>
                    </select>
                </div>
                
                <div id="queryFields"></div>
                
                <div class="form-group" id="captchaGroup" style="display: none;">
                    <label>验证码：</label>
                    <div class="captcha-group">
                        <div class="captcha-input">
                            <input type="text" id="captchaInput" class="form-control" placeholder="请输入验证码" maxlength="4">
                        </div>
                        <div class="captcha-img" id="captchaImg" onclick="refreshCaptcha()">
                            点击刷新
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn">查询数据</button>
                </div>
            </form>
            
            <div class="results-container" id="resultsContainer">
                <div class="results-header">
                    <h3>查询结果</h3>
                    <div class="view-toggle">
                        <button class="view-btn active" onclick="switchView('horizontal')">横向表格</button>
                        <button class="view-btn" onclick="switchView('vertical')">竖向表格</button>
                        <button class="close-btn" onclick="closeResults()">关闭结果</button>
                    </div>
                </div>
                <div class="table-container" id="tableContainer"></div>
                <div class="pagination" id="pagination"></div>
            </div>
        </div>
    </div>
    
    <div class="toast" id="toast"></div>
    
    <div class="modal" id="imageModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal()">&times;</span>
            <img id="modalImage" style="max-width: 100%; max-height: 80vh;">
        </div>
    </div>
    
    <div class="modal" id="linkModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal()">&times;</span>
            <div id="linkContent"></div>
        </div>
    </div>
    
    <script>
        let currentFile = null;
        let currentView = 'horizontal';
        let currentPage = 1;
        let totalPages = 1;
        
        // 页面加载时获取文件列表
        window.onload = function() {
            loadFileList();
        };
        
        // 加载文件列表
        function loadFileList() {
            fetch('?do=list')
                .then(response => response.json())
                .then(data => {
                    const fileSelect = document.getElementById('fileSelect');
                    fileSelect.innerHTML = '<option value="">请选择数据文件...</option>';
                    
                    if (data.success && data.files.length > 0) {
                        data.files.forEach(file => {
                            const option = document.createElement('option');
                            option.value = file.name;
                            option.textContent = file.title;
                            fileSelect.appendChild(option);
                        });
                        
                        // 自动选择第一个文件
                        fileSelect.selectedIndex = 1;
                        fileSelect.dispatchEvent(new Event('change'));
                    }
                })
                .catch(error => {
                    showToast('加载文件列表失败: ' + error.message);
                });
        }
        
        // 文件选择变化
        document.getElementById('fileSelect').addEventListener('change', function() {
            const fileName = this.value;
            if (fileName) {
                loadFileFields(fileName);
            } else {
                document.getElementById('queryFields').innerHTML = '';
                document.getElementById('captchaGroup').style.display = 'none';
            }
        });
        
        // 加载文件字段
        function loadFileFields(fileName) {
            fetch('?do=list')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const file = data.files.find(f => f.name === fileName);
                        if (file) {
                            currentFile = file;
                            displayQueryFields(file.queryFields);
                            
                            // 显示验证码
                            if ('{{.Ismas}}' === '1') {
                                document.getElementById('captchaGroup').style.display = 'block';
                                refreshCaptcha();
                            }
                        }
                    }
                })
                .catch(error => {
                    showToast('加载文件字段失败: ' + error.message);
                });
        }
        
        // 显示查询字段
        function displayQueryFields(fields) {
            const container = document.getElementById('queryFields');
            container.innerHTML = '';
            
            fields.forEach(field => {
                const div = document.createElement('div');
                div.className = 'form-group';
                div.innerHTML = '<label for="field_' + field + '">' + field + '：</label>' +
                    '<input type="text" id="field_' + field + '" name="' + field + '" class="form-control" placeholder="请输入' + field + '" required>';
                container.appendChild(div);
            });
        }
        
        // 刷新验证码
        function refreshCaptcha() {
            fetch('?do=code')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('captchaImg').textContent = data.code;
                    }
                })
                .catch(error => {
                    showToast('获取验证码失败: ' + error.message);
                });
        }
        
        // 表单提交
        document.getElementById('queryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!currentFile) {
                showToast('请选择数据文件');
                return;
            }
            
            const formData = new FormData();
            formData.append('file', currentFile.name);
            formData.append('page', '1');
            
            // 添加查询条件
            const queryFields = document.querySelectorAll('#queryFields input');
            queryFields.forEach(input => {
                if (input.value.trim()) {
                    formData.append(input.name, input.value.trim());
                }
            });
            
            // 添加验证码
            if ('{{.Ismas}}' === '1') {
                const captcha = document.getElementById('captchaInput').value;
                if (!captcha) {
                    showToast('请输入验证码');
                    return;
                }
                formData.append('captcha', captcha);
            }
            
            // 检查是否所有必填字段都已填写
            const requiredFields = document.querySelectorAll('#queryFields input[required]');
            let allFilled = true;
            requiredFields.forEach(input => {
                if (!input.value.trim()) {
                    allFilled = false;
                    input.focus();
                }
            });
            
            if (!allFilled) {
                showToast('请填写所有查询条件');
                return;
            }
            
            // 发送查询请求
            fetch('?do=cha', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayResults(data);
                    document.getElementById('queryForm').style.display = 'none';
                    document.getElementById('resultsContainer').style.display = 'block';
                } else {
                    showToast(data.message);
                    if ('{{.Ismas}}' === '1') {
                        refreshCaptcha();
                    }
                }
            })
            .catch(error => {
                showToast('查询失败: ' + error.message);
            });
        });
        
        // 显示查询结果
        function displayResults(data) {
            currentPage = data.page;
            totalPages = data.pages;
            
            const tableContainer = document.getElementById('tableContainer');
            const pagination = document.getElementById('pagination');
            
            if (data.data.length === 0) {
                tableContainer.innerHTML = '<p style="text-align: center; padding: 40px; color: #666;">没有找到匹配的数据</p>';
                pagination.innerHTML = '';
                return;
            }
            
            // 创建表格
            const table = createTable(data.data);
            tableContainer.innerHTML = '';
            tableContainer.appendChild(table);
            
            // 创建分页
            createPagination();
        }
        
        // 创建表格
        function createTable(data) {
            const table = document.createElement('table');
            
            if (currentView === 'horizontal') {
                // 横向表格
                data.forEach((row, rowIndex) => {
                    const tr = document.createElement('tr');
                    row.forEach((cell, cellIndex) => {
                        const element = rowIndex === 0 ? document.createElement('th') : document.createElement('td');
                        
                        if (rowIndex === 0) {
                            element.textContent = cell;
                        } else {
                            const fieldName = data[0][cellIndex];
                            if (isImageField(fieldName)) {
                                if (cell) {
                                    const img = document.createElement('img');
                                    img.src = cell;
                                    img.className = 'image-preview';
                                    img.onclick = () => showImageModal(cell);
                                    element.appendChild(img);
                                }
                            } else if (isUrlField(fieldName)) {
                                if (cell) {
                                    const link = document.createElement('span');
                                    link.className = 'link-text';
                                    link.textContent = cell;
                                    link.onclick = () => showLinkModal(cell);
                                    element.appendChild(link);
                                }
                            } else {
                                element.textContent = cell;
                            }
                        }
                        tr.appendChild(element);
                    });
                    table.appendChild(tr);
                });
            } else {
                // 竖向表格
                data[0].forEach((fieldName, fieldIndex) => {
                    const tr = document.createElement('tr');
                    
                    const th = document.createElement('th');
                    th.textContent = fieldName;
                    tr.appendChild(th);
                    
                    const td = document.createElement('td');
                    if (data.length > 1) {
                        const cell = data[1][fieldIndex];
                        if (isImageField(fieldName)) {
                            if (cell) {
                                const img = document.createElement('img');
                                img.src = cell;
                                img.className = 'image-preview';
                                img.onclick = () => showImageModal(cell);
                                td.appendChild(img);
                            }
                        } else if (isUrlField(fieldName)) {
                            if (cell) {
                                const link = document.createElement('span');
                                link.className = 'link-text';
                                link.textContent = cell;
                                link.onclick = () => showLinkModal(cell);
                                td.appendChild(link);
                            }
                        } else {
                            td.textContent = cell;
                        }
                    }
                    tr.appendChild(td);
                    
                    table.appendChild(tr);
                });
            }
            
            return table;
        }
        
        // 创建分页
        function createPagination() {
            const pagination = document.getElementById('pagination');
            pagination.innerHTML = '';
            
            if (totalPages <= 1) return;
            
            // 第一页
            const firstBtn = createPageBtn('第一页', 1, currentPage === 1);
            pagination.appendChild(firstBtn);
            
            // 上一页
            const prevBtn = createPageBtn('上一页', currentPage - 1, currentPage === 1);
            pagination.appendChild(prevBtn);
            
            // 页码选择
            const select = document.createElement('select');
            select.className = 'page-select';
            select.onchange = function() {
                goToPage(parseInt(this.value));
            };
            
            for (let i = 1; i <= totalPages; i++) {
                const option = document.createElement('option');
                option.value = i;
                option.textContent = '第' + i + '页';
                if (i === currentPage) option.selected = true;
                select.appendChild(option);
            }
            pagination.appendChild(select);
            
            // 下一页
            const nextBtn = createPageBtn('下一页', currentPage + 1, currentPage === totalPages);
            pagination.appendChild(nextBtn);
            
            // 最后页
            const lastBtn = createPageBtn('最后页', totalPages, currentPage === totalPages);
            pagination.appendChild(lastBtn);
        }
        
        // 创建分页按钮
        function createPageBtn(text, page, disabled) {
            const btn = document.createElement('a');
            btn.className = 'page-btn';
            if (disabled) btn.className += ' disabled';
            btn.textContent = text;
            if (!disabled) {
                btn.onclick = function(e) {
                    e.preventDefault();
                    goToPage(page);
                };
            }
            return btn;
        }
        
        // 跳转到指定页
        function goToPage(page) {
            if (page < 1 || page > totalPages || page === currentPage) return;
            
            const formData = new FormData();
            formData.append('file', currentFile.name);
            formData.append('page', page.toString());
            
            // 添加查询条件
            const queryFields = document.querySelectorAll('#queryFields input');
            queryFields.forEach(input => {
                if (input.value.trim()) {
                    formData.append(input.name, input.value.trim());
                }
            });
            
            fetch('?do=cha', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayResults(data);
                } else {
                    showToast(data.message);
                }
            })
            .catch(error => {
                showToast('查询失败: ' + error.message);
            });
        }
        
        // 切换视图
        function switchView(view) {
            currentView = view;
            
            // 更新按钮状态
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // 重新显示结果
            if (document.getElementById('resultsContainer').style.display !== 'none') {
                const formData = new FormData();
                formData.append('file', currentFile.name);
                formData.append('page', currentPage.toString());
                
                const queryFields = document.querySelectorAll('#queryFields input');
                queryFields.forEach(input => {
                    if (input.value.trim()) {
                        formData.append(input.name, input.value.trim());
                    }
                });
                
                fetch('?do=cha', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayResults(data);
                    }
                });
            }
        }
        
        // 关闭结果
        function closeResults() {
            document.getElementById('resultsContainer').style.display = 'none';
            document.getElementById('queryForm').style.display = 'block';
            document.getElementById('tableContainer').innerHTML = '';
            document.getElementById('pagination').innerHTML = '';
        }
        
        // 显示图片模态框
        function showImageModal(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('imageModal').style.display = 'block';
        }
        
        // 显示链接模态框
        function showLinkModal(url) {
            const content = document.getElementById('linkContent');
            content.innerHTML = '<h3>即将访问外站</h3>' +
                '<p>访问按钮：</p>' +
                '<a href="' + url + '" target="_blank" style="display: inline-block; background: #4facfe; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 10px 0;">访问链接</a>' +
                '<p>链接如下：</p>' +
                '<p style="word-break: break-all; background: #f8f9fa; padding: 10px; border-radius: 5px;">' + url + '</p>';
            document.getElementById('linkModal').style.display = 'block';
        }
        
        // 关闭模态框
        function closeModal() {
            document.getElementById('imageModal').style.display = 'none';
            document.getElementById('linkModal').style.display = 'none';
        }
        
        // 检查是否为图片字段
        function isImageField(fieldName) {
            const imageFields = {{.Isimg}};
            return imageFields.includes(fieldName);
        }
        
        // 检查是否为链接字段
        function isUrlField(fieldName) {
            const urlFields = {{.Isurl}};
            return urlFields.includes(fieldName);
        }
        
        // 显示提示消息
        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
        
        // 点击模态框外部关闭
        window.onclick = function(event) {
            const imageModal = document.getElementById('imageModal');
            const linkModal = document.getElementById('linkModal');
            if (event.target === imageModal) {
                closeModal();
            }
            if (event.target === linkModal) {
                closeModal();
            }
        }
    </script>
</body>
</html>`
	
	t, err := template.New("home").Parse(tmpl)
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}
	
	err = t.Execute(w, config)
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}
}

// 文件列表处理器
func listHandler(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	
	files, err := getFileList()
	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{
			"success": false,
			"message": "获取文件列表失败: " + err.Error(),
		})
		return
	}
	
	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"files":   files,
	})
}

// 验证码处理器
func codeHandler(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	
	// 生成验证码
	code := generateCaptcha()
	sessionID := r.Header.Get("X-Session-ID")
	if sessionID == "" {
		sessionID = fmt.Sprintf("%d", time.Now().UnixNano())
	}
	
	// 存储验证码
	captchaMap[sessionID] = Captcha{
		Code: code,
		Time: time.Now().Unix(),
	}
	
	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"code":    code,
		"session": sessionID,
	})
}

// 查询处理器
func queryHandler(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	
	if r.Method != "POST" {
		json.NewEncoder(w).Encode(map[string]interface{}{
			"success": false,
			"message": "请求方法错误",
		})
		return
	}
	
	// 解析表单数据
	err := r.ParseForm()
	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{
			"success": false,
			"message": "解析表单数据失败: " + err.Error(),
		})
		return
	}
	
	// 检查验证码
	if config.Ismas == "1" {
		sessionID := r.FormValue("session")
		captcha := r.FormValue("captcha")
		if sessionID == "" {
			// 如果没有session，尝试从所有session中查找匹配的验证码
			found := false
			for _, captchaData := range captchaMap {
				if captchaData.Code == captcha && time.Now().Unix()-captchaData.Time < 300 {
					found = true
					break
				}
			}
			if !found {
				json.NewEncoder(w).Encode(map[string]interface{}{
					"success": false,
					"message": "验证码错误",
				})
				return
			}
		} else if !checkCaptcha(sessionID, captcha) {
			json.NewEncoder(w).Encode(map[string]interface{}{
				"success": false,
				"message": "验证码错误",
			})
			return
		}
	}
	
	// 获取文件名
	filename := r.FormValue("file")
	if filename == "" {
		json.NewEncoder(w).Encode(map[string]interface{}{
			"success": false,
			"message": "请选择数据文件",
		})
		return
	}
	
	// 获取页码
	pageStr := r.FormValue("page")
	page := 1
	if pageStr != "" {
		if p, err := strconv.Atoi(pageStr); err == nil && p > 0 {
			page = p
		}
	}
	
	// 获取查询条件
	conditions := make(map[string]string)
	for _, field := range config.Itiao {
		if value := r.FormValue(field); value != "" {
			conditions[field] = value
		}
	}
	
	// 检查是否所有查询条件都已填写
	if config.Baoha == "1" {
		for _, field := range config.Itiao {
			if _, exists := conditions[field]; !exists {
				json.NewEncoder(w).Encode(map[string]interface{}{
					"success": false,
					"message": "请填写所有查询条件: " + field,
				})
				return
			}
		}
	}
	
	// 执行查询
	result, err := queryData(filename, conditions, page)
	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{
			"success": false,
			"message": "查询失败: " + err.Error(),
		})
		return
	}
	
	json.NewEncoder(w).Encode(result)
}

func main() {
	initConfig()
	
	http.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		do := r.URL.Query().Get("do")
		switch do {
		case "list":
			listHandler(w, r)
		case "code":
			codeHandler(w, r)
		case "cha":
			queryHandler(w, r)
		default:
			homeHandler(w, r)
		}
	})
	
	fmt.Println("服务器启动在 http://localhost:8084")
	log.Fatal(http.ListenAndServe(":8084", nil))
}