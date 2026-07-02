/* AuthXphp Admin 脚本
 * 提供最小化的 layui 兼容层（实际用到的部分有：tab 切换、导航菜单、弹窗）
 */
window.layui = window.layui || {};

// 简易 Tab
document.addEventListener('click', function (e) {
  var title = e.target.closest('.layui-tab-title > li');
  if (!title) return;
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
document.addEventListener('mouseover', function (e) {
  var item = e.target.closest('.layui-nav-item');
  if (!item) return;
  var child = item.querySelector('.layui-nav-child');
  if (child) child.style.display = 'block';
});
document.addEventListener('mouseout', function (e) {
  var item = e.target.closest('.layui-nav-item');
  if (!item) return;
  var child = item.querySelector('.layui-nav-child');
  if (child) child.style.display = '';
});

// 简易 alert
window.authxphpAlert = function (msg, type) {
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
