/**
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
window.MathJax = {
  tex: {
    inlineMath: [ ['$','$'], ['\\(','\\)'] ],
    displayMath: [ ['$$','$$'], ['\\[','\\]'] ],
    processEscapes: true,
    packages: ['base', 'ams','autoload'],
  },
  svg: {
    fontCache: 'global'
  }
};


(function () {
  var script = document.createElement('script');
  //script.src = 'app/js/mathjax/es5/tex-chtml-full.js';
  script.src = 'app/js/mathjax/es5/tex-svg-full.js';
  script.async = true;
  document.head.appendChild(script);
})();
