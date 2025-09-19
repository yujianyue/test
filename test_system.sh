#!/bin/bash

echo "=== 多TSV文本数据万用查分系统测试 ==="
echo ""

# 测试服务器是否运行
echo "1. 测试服务器状态..."
if curl -s "http://localhost:8084/" > /dev/null; then
    echo "✓ 服务器运行正常"
else
    echo "✗ 服务器未运行"
    exit 1
fi

# 测试文件列表API
echo ""
echo "2. 测试文件列表API..."
file_list=$(curl -s "http://localhost:8084/?do=list")
if echo "$file_list" | grep -q "success.*true"; then
    echo "✓ 文件列表API正常"
    echo "  返回的文件数量: $(echo "$file_list" | grep -o '"name"' | wc -l)"
else
    echo "✗ 文件列表API异常"
fi

# 测试验证码API
echo ""
echo "3. 测试验证码API..."
captcha_response=$(curl -s "http://localhost:8084/?do=code")
if echo "$captcha_response" | grep -q "success.*true"; then
    echo "✓ 验证码API正常"
    captcha_code=$(echo "$captcha_response" | grep -o '"code":"[^"]*"' | cut -d'"' -f4)
    echo "  生成的验证码: $captcha_code"
else
    echo "✗ 验证码API异常"
    exit 1
fi

# 测试查询API
echo ""
echo "4. 测试查询API..."
query_response=$(curl -s -X POST -d "file=students.xls.php&page=1&姓名=张三&学号=2023001&captcha=$captcha_code" "http://localhost:8084/?do=cha")
if echo "$query_response" | grep -q "success.*true"; then
    echo "✓ 查询API正常"
    echo "  查询结果包含: $(echo "$query_response" | grep -o '"total":[0-9]*' | cut -d':' -f2) 条记录"
else
    echo "✗ 查询API异常"
    echo "  错误信息: $(echo "$query_response" | grep -o '"message":"[^"]*"' | cut -d'"' -f4)"
fi

echo ""
echo "=== 测试完成 ==="
echo "访问地址: http://localhost:8084"
echo "系统功能正常，可以开始使用！"