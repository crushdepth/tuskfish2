/* tide — colour-scheme bootstrap.
 *
 * Runs blocking in <head> so the theme is fixed before the first paint (a
 * deferred script would flash the wrong scheme). Picks the saved choice if the
 * visitor has set one, otherwise follows the OS preference. Also marks <html>
 * with .js so the (script-dependent) theme toggle can reveal itself.
 */
(function () {
  'use strict';
  var root = document.documentElement;
  root.classList.add('js');
  var theme;
  try {
    theme = localStorage.getItem('tide-theme');
  } catch (e) {}
  if (theme !== 'dark' && theme !== 'light') {
    theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  root.setAttribute('data-theme', theme);
})();
