// Setup CSRF header for any jQuery AJAX calls
(function () {
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  if (window.jQuery && token) {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': token } });
  }
})();
setTimeout(() => {
  document.querySelectorAll('.alert').forEach(el => {
    debugger;
    if(el.classList.contains('non-dismissible')) return;
    el.remove();
  });
}, 5000);
