/**
 * 本文件功能: 公共JavaScript函数库
 * 作者信息: 15058593138@qq.com(手机号同微信)
 */

// Ajax通信函数
function ajax(url, data, callback, method) {
    method = method || 'POST';
    var xhr = new XMLHttpRequest();
    
    if (method.toUpperCase() === 'GET' && data) {
        url += '?' + objToUrl(data);
        data = null;
    } else if (method.toUpperCase() === 'POST' && data) {
        data = objToUrl(data);
    }
    
    xhr.open(method, url, true);
    if (method.toUpperCase() === 'POST') {
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    }
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    callback(response);
                } catch (e) {
                    callback({code: 0, msg: '响应数据格式错误'});
                }
            } else {
                callback({code: 0, msg: '请求失败'});
            }
        }
    };
    
    xhr.send(data);
}

// 对象转URL参数
function objToUrl(obj) {
    var arr = [];
    for (var key in obj) {
        arr.push(encodeURIComponent(key) + '=' + encodeURIComponent(obj[key]));
    }
    return arr.join('&');
}

// 获取指定表单的所有值
function getFormData(formId) {
    var form = document.getElementById(formId);
    if (!form) return {};
    
    var data = {};
    var elements = form.elements;
    
    for (var i = 0; i < elements.length; i++) {
        var el = elements[i];
        if (el.name) {
            if (el.type === 'checkbox') {
                if (!data[el.name]) {
                    data[el.name] = [];
                }
                if (el.checked) {
                    data[el.name].push(el.value);
                }
            } else if (el.type === 'radio') {
                if (el.checked) {
                    data[el.name] = el.value;
                }
            } else if (el.type !== 'button' && el.type !== 'submit') {
                data[el.name] = el.value;
            }
        }
    }
    
    // 将checkbox数组转为逗号分隔字符串
    for (var key in data) {
        if (Array.isArray(data[key])) {
            data[key] = data[key].join(',');
        }
    }
    
    return data;
}

// 吐司提示
function toast(msg, duration) {
    duration = duration || 2000;
    
    var toastEl = document.createElement('div');
    toastEl.className = 'toast';
    toastEl.textContent = msg;
    document.body.appendChild(toastEl);
    
    setTimeout(function() {
        toastEl.className = 'toast show';
    }, 10);
    
    setTimeout(function() {
        toastEl.className = 'toast';
        setTimeout(function() {
            document.body.removeChild(toastEl);
        }, 300);
    }, duration);
}

// 显示遮罩层
function showModal(title, content, buttons) {
    var modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.id = 'modalOverlay';
    
    var html = '<div class="modal-box">';
    html += '<div class="modal-header">';
    html += '<span class="modal-title">' + title + '</span>';
    html += '<span class="modal-close" onclick="closeModal()">&times;</span>';
    html += '</div>';
    html += '<div class="modal-body">' + content + '</div>';
    
    if (buttons && buttons.length > 0) {
        html += '<div class="modal-footer">';
        for (var i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            html += '<button class="btn ' + (btn.class || '') + '" onclick="' + (btn.onclick || 'closeModal()') + '">' + btn.text + '</button>';
        }
        html += '</div>';
    }
    
    html += '</div>';
    modal.innerHTML = html;
    document.body.appendChild(modal);
    
    setTimeout(function() {
        modal.className = 'modal-overlay show';
    }, 10);
}

// 关闭遮罩层
function closeModal() {
    var modal = document.getElementById('modalOverlay');
    if (modal) {
        modal.className = 'modal-overlay';
        setTimeout(function() {
            document.body.removeChild(modal);
        }, 300);
    }
}

// 确认对话框
function confirm(msg, callback) {
    showModal('确认', msg, [
        {text: '取消', class: 'btn-default', onclick: 'closeModal()'},
        {text: '确定', class: 'btn-primary', onclick: 'closeModal();(' + callback.toString() + ')()'}
    ]);
}

// 分页函数
var currentPage = 1;
var totalPage = 1;

function initPagination(page, total) {
    currentPage = parseInt(page);
    totalPage = parseInt(total);
    renderPagination();
}

function renderPagination() {
    var html = '';
    
    // 首页
    if (currentPage === 1 || totalPage === 0) {
        html += '<span class="page-btn disabled">首页</span>';
    } else {
        html += '<span class="page-btn" onclick="gotoPage(1)">首页</span>';
    }
    
    // 上一页
    if (currentPage === 1 || totalPage === 0) {
        html += '<span class="page-btn disabled">上一页</span>';
    } else {
        html += '<span class="page-btn" onclick="gotoPage(' + (currentPage - 1) + ')">上一页</span>';
    }
    
    // 页码选择
    if (totalPage > 0) {
        html += '<select class="page-select" onchange="gotoPage(this.value)">';
        for (var i = 1; i <= totalPage; i++) {
            html += '<option value="' + i + '"' + (i === currentPage ? ' selected' : '') + '>第' + i + '页</option>';
        }
        html += '</select>';
    }
    
    // 下一页
    if (currentPage === totalPage || totalPage === 0) {
        html += '<span class="page-btn disabled">下一页</span>';
    } else {
        html += '<span class="page-btn" onclick="gotoPage(' + (currentPage + 1) + ')">下一页</span>';
    }
    
    // 末页
    if (currentPage === totalPage || totalPage === 0) {
        html += '<span class="page-btn disabled">末页</span>';
    } else {
        html += '<span class="page-btn" onclick="gotoPage(' + totalPage + ')">末页</span>';
    }
    
    var pageBox = document.getElementById('pagination');
    if (pageBox) {
        pageBox.innerHTML = html;
    }
}

function gotoPage(page) {
    // 此函数需要在具体页面中实现，用于加载对应页码的数据
    console.log('跳转到第' + page + '页');
}

// Cookie操作
function setCookie(name, value, days) {
    var expires = '';
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = '; expires=' + date.toUTCString();
    }
    document.cookie = name + '=' + (value || '') + expires + '; path=/';
}

function getCookie(name) {
    var nameEQ = name + '=';
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
}

function deleteCookie(name) {
    document.cookie = name + '=; Max-Age=-99999999;';
}

// 格式化时间
function formatDate(timestamp, format) {
    var date = new Date(timestamp);
    format = format || 'Y-m-d H:i:s';
    
    var year = date.getFullYear();
    var month = ('0' + (date.getMonth() + 1)).slice(-2);
    var day = ('0' + date.getDate()).slice(-2);
    var hour = ('0' + date.getHours()).slice(-2);
    var minute = ('0' + date.getMinutes()).slice(-2);
    var second = ('0' + date.getSeconds()).slice(-2);
    
    format = format.replace('Y', year);
    format = format.replace('m', month);
    format = format.replace('d', day);
    format = format.replace('H', hour);
    format = format.replace('i', minute);
    format = format.replace('s', second);
    
    return format;
}

// 防抖函数
function debounce(func, wait) {
    var timeout;
    return function() {
        var context = this;
        var args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(function() {
            func.apply(context, args);
        }, wait);
    };
}

// 节流函数
function throttle(func, wait) {
    var previous = 0;
    return function() {
        var now = Date.now();
        var context = this;
        var args = arguments;
        if (now - previous > wait) {
            func.apply(context, args);
            previous = now;
        }
    };
}
