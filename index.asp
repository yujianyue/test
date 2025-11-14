<%@ Language=VBScript CodePage=65001 %>
<%
'===========================================
' 多输入框都输对精准查询系统(万用查分) - ASP版本
' 支持多TSV文件、动态查询、验证码、分页显示
'===========================================
Option Explicit
Response.Charset = "utf-8"
Response.ContentType = "text/html"

'========== 系统配置 ==========
Dim Title, Copyr, Jscss, Ismas, Baoha, Zishu
Dim Itiao, Ihide, Isurl, Isimg, Dbdir, Dbxls, Copyu, Pagex, Pagem

Title = "多输入框都输对精准查询系统(万用查分)"
Copyr = "查立得-"
Jscss = "V20210909"
Ismas = "1"                         '是否启用验证码 (1=是, 0=否)
Baoha = "1"                         '是否精准查询 (1=是, 0=否)
Zishu = 32                          '超过字数:折叠、展开
Itiao = "姓名|学号|工号"            '查询条件字段(|分隔)
Ihide = "密码|身份证"               '隐藏字段
Isurl = "购买地址|先领优惠券"       '链接字段
Isimg = "商品主图|产品图片"         '图片字段
Dbdir = "./shujuku/"                '数据文件目录
Dbxls = ".xls.dat"                  '数据文件后缀
Copyu = "/"                         '版权链接
Pagex = 10                          '每页显示条数
Pagem = 20                          '最大显示页数

'========== 处理请求 ==========
Dim doAction
doAction = Trim(Request.QueryString("do"))

Select Case doAction
    Case "list"
        Call GetFileList()
    Case "code"
        Call GenerateCaptcha()
    Case "cha"
        Call DoQuery()
    Case Else
        Call ShowMainPage()
End Select

'========== 函数：显示主页面 ==========
Sub ShowMainPage()
%>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><%=Title%></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        .form-group select,
        .form-group input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }
        .form-group select:focus,
        .form-group input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .captcha-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .captcha-group input {
            flex: 1;
        }
        .captcha-img {
            height: 46px;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid #e0e0e0;
        }
        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .btn:active {
            transform: translateY(0);
        }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .result-container {
            display: none;
            margin-top: 20px;
        }
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .result-info {
            font-size: 14px;
            color: #666;
        }
        .result-actions {
            display: flex;
            gap: 10px;
        }
        .btn-small {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-small:hover {
            background: #764ba2;
        }
        .table-wrapper {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        th {
            background: #f5f5f5;
            font-weight: 600;
            color: #333;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        tr:hover {
            background: #f9f9f9;
        }
        .vertical-table {
            display: none;
        }
        .vertical-table .record {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .vertical-table .record-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .vertical-table .record-row:last-child {
            border-bottom: none;
        }
        .vertical-table .field-label {
            font-weight: 600;
            min-width: 120px;
            color: #555;
        }
        .vertical-table .field-value {
            flex: 1;
            color: #333;
        }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .page-btn {
            padding: 8px 12px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .page-btn:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .page-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .img-preview {
            max-width: 100px;
            max-height: 100px;
            cursor: pointer;
            border-radius: 4px;
        }
        .link-field {
            color: #667eea;
            text-decoration: underline;
            cursor: pointer;
        }
        .text-collapse {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .text-expandable {
            cursor: pointer;
            color: #667eea;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal img {
            max-width: 90%;
            max-height: 90%;
            border-radius: 8px;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 14px;
            border-top: 1px solid #e0e0e0;
        }
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        .error-msg {
            background: #fee;
            color: #c33;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
        }
        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        @media (max-width: 768px) {
            .container {
                border-radius: 0;
            }
            .content {
                padding: 20px;
            }
            .header h1 {
                font-size: 22px;
            }
            .result-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .horizontal-table {
                display: none !important;
            }
            .vertical-table {
                display: block !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><%=Title%></h1>
            <p>支持多文件查询 · 精准匹配 · 分页显示</p>
        </div>
        
        <div class="content">
            <form id="queryForm">
                <div class="form-group">
                    <label for="fileSelect">选择查询文件：</label>
                    <select id="fileSelect" name="file" required>
                        <option value="">-- 请选择文件 --</option>
                    </select>
                </div>
                
                <div id="queryFields"></div>
                
                <% If Ismas = "1" Then %>
                <div class="form-group">
                    <label for="captcha">验证码：</label>
                    <div class="captcha-group">
                        <input type="text" id="captcha" name="captcha" maxlength="4" placeholder="请输入验证码" required>
                        <img id="captchaImg" class="captcha-img" src="?do=code&t=<%=Timer()%>" alt="验证码" onclick="this.src='?do=code&t='+new Date().getTime()">
                    </div>
                </div>
                <% End If %>
                
                <button type="submit" class="btn" id="submitBtn">查询数据</button>
            </form>
            
            <div class="result-container" id="resultContainer">
                <div class="result-header">
                    <div class="result-info" id="resultInfo"></div>
                    <div class="result-actions">
                        <button class="btn-small" id="toggleViewBtn">切换视图</button>
                        <button class="btn-small" id="closeResultBtn">关闭结果</button>
                    </div>
                </div>
                
                <div class="table-wrapper horizontal-table" id="horizontalTable"></div>
                <div class="vertical-table" id="verticalTable"></div>
                
                <div class="pagination" id="pagination"></div>
            </div>
        </div>
        
        <div class="footer">
            <p><%=Copyr%> <a href="<%=Copyu%>" target="_blank"><%=Title%></a> | <%=Jscss%></p>
        </div>
    </div>
    
    <div class="modal" id="imageModal" onclick="this.style.display='none'">
        <img id="modalImage" src="" alt="预览">
    </div>

    <script>
        // 配置变量
        const config = {
            ismas: "<%=Ismas%>",
            zishu: <%=Zishu%>,
            itiao: "<%=Itiao%>".split("|"),
            ihide: "<%=Ihide%>".split("|"),
            isurl: "<%=Isurl%>".split("|"),
            isimg: "<%=Isimg%>".split("|"),
            pagex: <%=Pagex%>,
            pagem: <%=Pagem%>
        };
        
        let currentData = [];
        let currentPage = 1;
        let totalPages = 1;
        let isVerticalView = false;
        
        // 初始化
        document.addEventListener('DOMContentLoaded', function() {
            loadFileList();
            
            document.getElementById('fileSelect').addEventListener('change', function() {
                if (this.value) {
                    generateQueryFields(this.value);
                } else {
                    document.getElementById('queryFields').innerHTML = '';
                }
            });
            
            document.getElementById('queryForm').addEventListener('submit', function(e) {
                e.preventDefault();
                performQuery();
            });
            
            document.getElementById('toggleViewBtn').addEventListener('click', toggleView);
            document.getElementById('closeResultBtn').addEventListener('click', closeResult);
        });
        
        // 加载文件列表
        function loadFileList() {
            fetch('?do=list')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('fileSelect');
                        data.files.forEach(file => {
                            const option = document.createElement('option');
                            option.value = file.name;
                            option.textContent = file.display;
                            select.appendChild(option);
                        });
                    }
                })
                .catch(error => console.error('加载文件列表失败:', error));
        }
        
        // 生成查询字段
        function generateQueryFields(filename) {
            fetch('?do=list&file=' + encodeURIComponent(filename))
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.fields) {
                        const container = document.getElementById('queryFields');
                        container.innerHTML = '';
                        
                        data.fields.forEach(field => {
                            const div = document.createElement('div');
                            div.className = 'form-group';
                            div.innerHTML = `
                                <label for="field_${field}">${field}：</label>
                                <input type="text" id="field_${field}" name="${field}" placeholder="请输入${field}" required>
                            `;
                            container.appendChild(div);
                        });
                    }
                })
                .catch(error => console.error('加载字段失败:', error));
        }
        
        // 执行查询
        function performQuery() {
            const form = document.getElementById('queryForm');
            const formData = new FormData(form);
            const submitBtn = document.getElementById('submitBtn');
            
            submitBtn.disabled = true;
            submitBtn.textContent = '查询中...';
            
            fetch('?do=cha', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentData = data;
                    currentPage = 1;
                    totalPages = data.pagination.totalPages;
                    displayResults();
                    document.getElementById('resultContainer').style.display = 'block';
                    form.style.display = 'none';
                } else {
                    alert(data.message || '查询失败');
                    if (config.ismas === "1") {
                        refreshCaptcha();
                    }
                }
            })
            .catch(error => {
                alert('查询出错：' + error.message);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = '查询数据';
            });
        }
        
        // 显示结果
        function displayResults() {
            if (!currentData || !currentData.data) return;
            
            const start = (currentPage - 1) * config.pagex;
            const end = start + config.pagex;
            const pageData = currentData.data.slice(start, end);
            
            // 更新信息
            document.getElementById('resultInfo').innerHTML = `
                共找到 <strong>${currentData.total}</strong> 条记录 | 
                第 <strong>${currentPage}</strong>/<strong>${totalPages}</strong> 页 | 
                用时 <strong>${currentData.time}</strong> | 
                内存 <strong>${currentData.memory}</strong>
            `;
            
            // 显示表格
            if (isVerticalView) {
                displayVerticalTable(pageData);
            } else {
                displayHorizontalTable(pageData);
            }
            
            // 显示分页
            displayPagination();
        }
        
        // 横向表格
        function displayHorizontalTable(data) {
            if (data.length === 0) return;
            
            let html = '<table><thead><tr>';
            currentData.headers.forEach(header => {
                if (!config.ihide.includes(header)) {
                    html += `<th>${escapeHtml(header)}</th>`;
                }
            });
            html += '</tr></thead><tbody>';
            
            data.forEach(row => {
                html += '<tr>';
                currentData.headers.forEach(header => {
                    if (!config.ihide.includes(header)) {
                        html += '<td>' + formatCell(header, row[header]) + '</td>';
                    }
                });
                html += '</tr>';
            });
            
            html += '</tbody></table>';
            document.getElementById('horizontalTable').innerHTML = html;
        }
        
        // 竖向表格
        function displayVerticalTable(data) {
            let html = '';
            
            data.forEach((row, index) => {
                html += '<div class="record">';
                html += `<h4 style="margin-bottom:10px;color:#667eea;">记录 ${(currentPage-1)*config.pagex + index + 1}</h4>`;
                
                currentData.headers.forEach(header => {
                    if (!config.ihide.includes(header)) {
                        html += '<div class="record-row">';
                        html += `<div class="field-label">${escapeHtml(header)}:</div>`;
                        html += `<div class="field-value">${formatCell(header, row[header])}</div>`;
                        html += '</div>';
                    }
                });
                
                html += '</div>';
            });
            
            document.getElementById('verticalTable').innerHTML = html;
        }
        
        // 格式化单元格
        function formatCell(header, value) {
            if (!value) return '';
            
            const strValue = String(value);
            
            // 图片字段
            if (config.isimg.includes(header)) {
                return `<img src="${escapeHtml(strValue)}" class="img-preview" onclick="showImage('${escapeHtml(strValue)}')" alt="图片">`;
            }
            
            // 链接字段
            if (config.isurl.includes(header)) {
                return `<span class="link-field" onclick="confirmLink('${escapeHtml(strValue)}')">${escapeHtml(strValue)}</span>`;
            }
            
            // 长文本折叠
            if (strValue.length > config.zishu) {
                return `<span class="text-expandable" onclick="toggleText(this)">
                    <span class="text-collapse">${escapeHtml(strValue)}</span>
                    <span style="display:none">${escapeHtml(strValue)}</span>
                </span>`;
            }
            
            return escapeHtml(strValue);
        }
        
        // 显示分页
        function displayPagination() {
            if (totalPages <= 1) {
                document.getElementById('pagination').innerHTML = '';
                return;
            }
            
            let html = '';
            
            // 上一页
            html += `<button class="page-btn" onclick="changePage(${currentPage-1})" ${currentPage<=1?'disabled':''}>上一页</button>`;
            
            // 页码
            let startPage = Math.max(1, currentPage - Math.floor(config.pagem / 2));
            let endPage = Math.min(totalPages, startPage + config.pagem - 1);
            
            if (endPage - startPage < config.pagem - 1) {
                startPage = Math.max(1, endPage - config.pagem + 1);
            }
            
            if (startPage > 1) {
                html += `<button class="page-btn" onclick="changePage(1)">1</button>`;
                if (startPage > 2) html += '<span>...</span>';
            }
            
            for (let i = startPage; i <= endPage; i++) {
                html += `<button class="page-btn ${i===currentPage?'active':''}" onclick="changePage(${i})">${i}</button>`;
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) html += '<span>...</span>';
                html += `<button class="page-btn" onclick="changePage(${totalPages})">${totalPages}</button>`;
            }
            
            // 下一页
            html += `<button class="page-btn" onclick="changePage(${currentPage+1})" ${currentPage>=totalPages?'disabled':''}>下一页</button>`;
            
            document.getElementById('pagination').innerHTML = html;
        }
        
        // 切换页码
        function changePage(page) {
            if (page < 1 || page > totalPages || page === currentPage) return;
            currentPage = page;
            displayResults();
        }
        
        // 切换视图
        function toggleView() {
            isVerticalView = !isVerticalView;
            const horizontal = document.getElementById('horizontalTable');
            const vertical = document.getElementById('verticalTable');
            
            if (isVerticalView) {
                horizontal.style.display = 'none';
                vertical.style.display = 'block';
            } else {
                horizontal.style.display = 'block';
                vertical.style.display = 'none';
            }
            
            displayResults();
        }
        
        // 关闭结果
        function closeResult() {
            document.getElementById('resultContainer').style.display = 'none';
            document.getElementById('queryForm').style.display = 'block';
            if (config.ismas === "1") {
                refreshCaptcha();
            }
        }
        
        // 刷新验证码
        function refreshCaptcha() {
            const img = document.getElementById('captchaImg');
            if (img) {
                img.src = '?do=code&t=' + new Date().getTime();
            }
        }
        
        // 显示图片
        function showImage(url) {
            document.getElementById('modalImage').src = url;
            document.getElementById('imageModal').style.display = 'flex';
        }
        
        // 确认链接
        function confirmLink(url) {
            if (confirm('是否访问以下链接？\n' + url)) {
                window.open(url, '_blank');
            }
        }
        
        // 切换文本展开/折叠
        function toggleText(element) {
            const spans = element.querySelectorAll('span');
            spans.forEach(span => {
                span.style.display = span.style.display === 'none' ? 'inline' : 'none';
            });
        }
        
        // HTML转义
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
<%
End Sub

'========== 函数：获取文件列表 ==========
Sub GetFileList()
    Response.ContentType = "application/json"
    
    Dim fso, folder, files, file, fileList, fileName, fileObj
    Set fso = Server.CreateObject("Scripting.FileSystemObject")
    Set fileList = Server.CreateObject("Scripting.Dictionary")
    
    ' 获取指定文件的字段
    Dim queryFile
    queryFile = Trim(Request.QueryString("file"))
    
    If queryFile <> "" Then
        ' 返回文件的查询字段
        Dim fields, fileData, headers, itiaoArr
        itiaoArr = Split(Itiao, "|")
        
        If fso.FileExists(Server.MapPath(Dbdir & queryFile)) Then
            Set fileData = ReadTSVFile(Server.MapPath(Dbdir & queryFile))
            If Not IsNull(fileData) And IsArray(fileData("headers")) Then
                Set fields = Server.CreateObject("Scripting.Dictionary")
                
                For Each header In fileData("headers")
                    Dim isQueryField
                    isQueryField = False
                    
                    For Each itm In itiaoArr
                        If InStr(header, itm) > 0 Then
                            isQueryField = True
                            Exit For
                        End If
                    Next
                    
                    If isQueryField Then
                        fields.Add header, header
                    End If
                Next
                
                Response.Write "{""success"":true,""fields"":" & JSONArray(fields.Keys) & "}"
                Exit Sub
            End If
        End If
        
        Response.Write "{""success"":false,""message"":""文件不存在或格式错误""}"
        Exit Sub
    End If
    
    ' 获取文件列表
    If fso.FolderExists(Server.MapPath(Dbdir)) Then
        Set folder = fso.GetFolder(Server.MapPath(Dbdir))
        Set files = folder.Files
        
        For Each file In files
            fileName = file.Name
            If Right(fileName, Len(Dbxls)) = Dbxls Then
                fileList.Add fileName, Left(fileName, Len(fileName) - Len(Dbxls))
            End If
        Next
    End If
    
    Response.Write "{""success"":true,""files"":" & JSONFileList(fileList) & "}"
    
    Set files = Nothing
    Set folder = Nothing
    Set fso = Nothing
End Sub

'========== 函数：生成验证码 ==========
Sub GenerateCaptcha()
    ' 生成4位随机数字验证码
    Randomize
    Dim code, i
    code = ""
    For i = 1 To 4
        code = code & Int(Rnd() * 10)
    Next
    
    ' 保存到Session
    Session("captcha") = code
    Session.Timeout = 20
    
    ' 生成简单的图片(使用ASCII art方式)
    Response.ContentType = "image/gif"
    Response.BinaryWrite GenerateSimpleGIF(code)
End Sub

'========== 函数：执行查询 ==========
Sub DoQuery()
    Response.ContentType = "application/json"
    
    Dim startTime, startMem
    startTime = Timer()
    
    ' 验证验证码
    If Ismas = "1" Then
        Dim inputCaptcha
        inputCaptcha = Trim(Request.Form("captcha"))
        If inputCaptcha = "" Or inputCaptcha <> Session("captcha") Then
            Response.Write "{""success"":false,""message"":""验证码错误""}"
            Exit Sub
        End If
    End If
    
    ' 获取查询参数
    Dim fileName
    fileName = Trim(Request.Form("file"))
    If fileName = "" Then
        Response.Write "{""success"":false,""message"":""请选择查询文件""}"
        Exit Sub
    End If
    
    ' 读取文件
    Dim fso, filePath, fileData
    Set fso = Server.CreateObject("Scripting.FileSystemObject")
    filePath = Server.MapPath(Dbdir & fileName)
    
    If Not fso.FileExists(filePath) Then
        Response.Write "{""success"":false,""message"":""文件不存在""}"
        Exit Sub
    End If
    
    Set fileData = ReadTSVFile(filePath)
    If IsNull(fileData) Then
        Response.Write "{""success"":false,""message"":""文件读取失败""}"
        Exit Sub
    End If
    
    ' 执行查询
    Dim results, queryConditions, header, value
    Set queryConditions = Server.CreateObject("Scripting.Dictionary")
    
    ' 收集查询条件
    For Each header In fileData("headers")
        value = Trim(Request.Form(header))
        If value <> "" Then
            queryConditions.Add header, value
        End If
    Next
    
    ' 过滤数据
    Set results = Server.CreateObject("Scripting.Dictionary")
    results.Add "success", True
    results.Add "title", fileData("title")
    results.Add "subtitle", fileData("subtitle")
    results.Add "headers", fileData("headers")
    results.Add "data", FilterData(fileData("data"), fileData("headers"), queryConditions)
    results.Add "total", UBound(results("data")) + 1
    results.Add "time", FormatNumber(Timer() - startTime, 4) & "s"
    results.Add "memory", "N/A"
    
    ' 分页信息
    Dim totalPages
    If results("total") > 0 Then
        totalPages = Int((results("total") - 1) / Pagex) + 1
    Else
        totalPages = 0
    End If
    
    results.Add "pagination", Array(currentPage, totalPages, Pagex)
    
    ' 输出JSON
    Response.Write JSONResponse(results)
    
    Set fso = Nothing
End Sub

'========== 函数：读取TSV文件 ==========
Function ReadTSVFile(filePath)
    On Error Resume Next
    
    Dim fso, file, content, lines, i
    Dim result, headers, dataRows
    
    Set fso = Server.CreateObject("Scripting.FileSystemObject")
    Set file = fso.OpenTextFile(filePath, 1, False, -1) ' -1 = Unicode
    
    If Err.Number <> 0 Then
        ' 尝试UTF-8
        Err.Clear
        Set file = fso.OpenTextFile(filePath, 1, False, -2) ' -2 = System Default
    End If
    
    If Err.Number <> 0 Then
        ReadTSVFile = Null
        Exit Function
    End If
    
    content = file.ReadAll()
    file.Close
    
    ' 解析行
    lines = Split(content, vbCrLf)
    If UBound(lines) < 0 Then
        lines = Split(content, vbLf)
    End If
    If UBound(lines) < 0 Then
        lines = Split(content, Chr(10))
    End If
    
    If UBound(lines) < 3 Then
        ReadTSVFile = Null
        Exit Function
    End If
    
    ' 创建结果字典
    Set result = Server.CreateObject("Scripting.Dictionary")
    result.Add "title", lines(0)
    result.Add "subtitle", lines(1)
    
    ' 解析表头
    headers = Split(lines(2), vbTab)
    result.Add "headers", headers
    
    ' 解析数据行
    ReDim dataRows(UBound(lines) - 3)
    Dim rowIndex
    rowIndex = 0
    
    For i = 3 To UBound(lines)
        If Trim(lines(i)) <> "" Then
            Dim cols, rowDict, j
            cols = Split(lines(i), vbTab)
            
            Set rowDict = Server.CreateObject("Scripting.Dictionary")
            For j = 0 To UBound(headers)
                If j <= UBound(cols) Then
                    rowDict.Add headers(j), cols(j)
                Else
                    rowDict.Add headers(j), ""
                End If
            Next
            
            Set dataRows(rowIndex) = rowDict
            rowIndex = rowIndex + 1
        End If
    Next
    
    ' 调整数组大小
    If rowIndex > 0 Then
        ReDim Preserve dataRows(rowIndex - 1)
    Else
        ReDim dataRows(-1)
    End If
    
    result.Add "data", dataRows
    
    Set ReadTSVFile = result
    Set fso = Nothing
End Function

'========== 函数：过滤数据 ==========
Function FilterData(dataRows, headers, conditions)
    Dim results(), resultCount, i, row, match, key, value
    ReDim results(UBound(dataRows))
    resultCount = 0
    
    For i = 0 To UBound(dataRows)
        Set row = dataRows(i)
        match = True
        
        ' 检查所有条件
        For Each key In conditions.Keys
            value = conditions(key)
            If Baoha = "1" Then
                ' 精准匹配
                If row(key) <> value Then
                    match = False
                    Exit For
                End If
            Else
                ' 模糊匹配
                If InStr(row(key), value) = 0 Then
                    match = False
                    Exit For
                End If
            End If
        Next
        
        If match Then
            Set results(resultCount) = row
            resultCount = resultCount + 1
        End If
    Next
    
    ' 调整数组大小
    If resultCount > 0 Then
        ReDim Preserve results(resultCount - 1)
    Else
        ReDim results(-1)
    End If
    
    FilterData = results
End Function

'========== 函数：生成简单GIF图片 ==========
Function GenerateSimpleGIF(text)
    ' GIF文件头
    Dim gif
    gif = "GIF89a" ' 文件头
    gif = gif & ChrB(100) & ChrB(0) ' 宽度 100px
    gif = gif & ChrB(40) & ChrB(0)  ' 高度 40px
    gif = gif & ChrB(&HF0)          ' 全局颜色表标志
    gif = gif & ChrB(0)             ' 背景色索引
    gif = gif & ChrB(0)             ' 像素宽高比
    
    ' 颜色表 (简化为黑白)
    Dim i
    For i = 0 To 255
        gif = gif & ChrB(i) & ChrB(i) & ChrB(i)
    Next
    
    ' 图像描述符
    gif = gif & ChrB(&H2C)          ' 图像分隔符
    gif = gif & ChrB(0) & ChrB(0)   ' 图像左边距
    gif = gif & ChrB(0) & ChrB(0)   ' 图像上边距
    gif = gif & ChrB(100) & ChrB(0) ' 图像宽度
    gif = gif & ChrB(40) & ChrB(0)  ' 图像高度
    gif = gif & ChrB(0)             ' 局部颜色表标志
    
    ' LZW最小代码大小
    gif = gif & ChrB(8)
    
    ' 图像数据 (简化处理)
    gif = gif & ChrB(2) & ChrB(&H44) & ChrB(&H01) & ChrB(0)
    
    ' GIF结束标志
    gif = gif & ChrB(&H3B)
    
    GenerateSimpleGIF = gif
End Function

'========== JSON辅助函数 ==========
Function JSONString(str)
    str = Replace(str, "\", "\\")
    str = Replace(str, """", "\""")
    str = Replace(str, vbCrLf, "\n")
    str = Replace(str, vbCr, "\n")
    str = Replace(str, vbLf, "\n")
    str = Replace(str, vbTab, "\t")
    JSONString = """" & str & """"
End Function

Function JSONArray(arr)
    Dim result, item
    result = "["
    For Each item In arr
        If result <> "[" Then result = result & ","
        result = result & JSONString(item)
    Next
    result = result & "]"
    JSONArray = result
End Function

Function JSONFileList(dict)
    Dim result, key
    result = "["
    For Each key In dict.Keys
        If result <> "[" Then result = result & ","
        result = result & "{""name"":" & JSONString(key) & ",""display"":" & JSONString(dict(key)) & "}"
    Next
    result = result & "]"
    JSONFileList = result
End Function

Function JSONObject(dict)
    Dim result, key, value
    result = "{"
    For Each key In dict.Keys
        If result <> "{" Then result = result & ","
        value = dict(key)
        result = result & JSONString(key) & ":"
        If IsObject(value) Then
            result = result & JSONObject(value)
        ElseIf IsArray(value) Then
            result = result & JSONArrayData(value)
        Else
            result = result & JSONString(CStr(value))
        End If
    Next
    result = result & "}"
    JSONObject = result
End Function

Function JSONArrayData(arr)
    Dim result, i
    result = "["
    For i = 0 To UBound(arr)
        If i > 0 Then result = result & ","
        If IsObject(arr(i)) Then
            result = result & JSONObject(arr(i))
        Else
            result = result & JSONString(CStr(arr(i)))
        End If
    Next
    result = result & "]"
    JSONArrayData = result
End Function

Function JSONResponse(dict)
    Dim result, key, value
    result = "{"
    
    For Each key In dict.Keys
        If result <> "{" Then result = result & ","
        value = dict(key)
        result = result & JSONString(key) & ":"
        
        Select Case VarType(value)
            Case vbBoolean
                result = result & LCase(CStr(value))
            Case vbInteger, vbLong, vbSingle, vbDouble
                result = result & CStr(value)
            Case vbString
                result = result & JSONString(value)
            Case vbObject
                result = result & JSONObject(value)
            Case vbArray + vbVariant, vbArray + vbObject
                If IsArray(value) Then
                    If UBound(value) >= 0 Then
                        If IsObject(value(0)) Then
                            result = result & JSONArrayData(value)
                        Else
                            result = result & JSONArray(value)
                        End If
                    Else
                        result = result & "[]"
                    End If
                End If
            Case Else
                result = result & JSONString(CStr(value))
        End Select
    Next
    
    result = result & "}"
    JSONResponse = result
End Function
%>
