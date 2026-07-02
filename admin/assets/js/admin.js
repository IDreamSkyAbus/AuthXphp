/* AuthXphp Admin 脚本
 * 提供最小化的 layui 兼容层（实际用到的部分有：tab 切换、导航菜单、弹窗）
 */
window.layui = window.layui || {};

// 简易 Tab
document.addEventListener('click', function (e) {
  var title = e.target.closest('.layui-tab-title > li');
  if (!title) return;
  // 已激活则不重复执行，避免快速点击造成的冗余 DOM 操作
  if (title.classList.contains('layui-this')) return;
  var tab = title.closest('.layui-tab');
  if (!tab) return;
  // 标题
  tab.querySelectorAll('.layui-tab-title > li').forEach(function (li) { li.classList.remove('layui-this'); });
  title.classList.add('layui-this');
  // 内容
  var idx = Array.prototype.indexOf.call(title.parentNode.children, title);
  tab.querySelectorAll('.layui-tab-item').forEach(function (item, i) {
    item.classList.toggle('layui-show', i === idx);
  });
});

// 下拉菜单 hover 展开
// 使用 mouseenter/mouseleave 而非 mouseover/mouseout，避免子元素冒泡导致的闪烁
document.addEventListener('mouseenter', function (e) {
  var item = e.target.closest('.layui-nav-item');
  if (!item) return;
  var child = item.querySelector('.layui-nav-child');
  if (child) child.style.display = 'block';
});
document.addEventListener('mouseleave', function (e) {
  var item = e.target.closest('.layui-nav-item');
  if (!item) return;
  var child = item.querySelector('.layui-nav-child');
  if (child) child.style.display = '';
});

// 简易 alert
// 安全提示：本函数必须使用 textContent / setAttribute('text', ...) 等安全 API 插入用户内容，
// 严禁使用 innerHTML / outerHTML / insertAdjacentHTML / document.write 拼接 msg 字符串，
// 否则会引入 XSS 漏洞。
// 开发模式下检测到误用 innerHTML 会被 console.warn 警告。
window.authxphpAlert = function (msg, type) {
  if (typeof console !== 'undefined' && console.warn) {
    var stack = (new Error()).stack || '';
    if (stack.indexOf('innerHTML') !== -1) {
      console.warn('[authxphpAlert] 检测到可能的 innerHTML 误用，请改用 textContent 以避免 XSS。');
    }
  }
  var div = document.createElement('div');
  div.className = 'layui-alert layui-alert-' + (type || 'info');
  div.textContent = msg;
  div.style.position = 'fixed';
  div.style.top = '20px';
  div.style.right = '20px';
  div.style.zIndex = '9999';
  div.style.minWidth = '200px';
  document.body.appendChild(div);
  setTimeout(function () { div.remove(); }, 3000);
};
