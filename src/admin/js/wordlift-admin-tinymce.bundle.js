!function(t){function e(r){if(n[r])return n[r].exports;var o=n[r]={i:r,l:!1,exports:{}};return t[r].call(o.exports,o,o.exports,e),o.l=!0,o.exports}var n={};e.m=t,e.c=n,e.i=function(t){return t},e.d=function(t,n,r){e.o(t,n)||Object.defineProperty(t,n,{configurable:!1,enumerable:!0,get:r})},e.n=function(t){var n=t&&t.__esModule?function(){return t.default}:function(){return t};return e.d(n,"a",n),n},e.o=function(t,e){return Object.prototype.hasOwnProperty.call(t,e)},e.p="",e(e.s=149)}({149:function(t,e,n){"use strict";Object.defineProperty(e,"__esModule",{value:!0});var r=n(36),o=jQuery;tinymce.PluginManager.add("wl_tinymce",function(t){t.on("KeyDown",function(){var e=o(t.getBody());e.addClass("wl-tinymce-typing"),n.i(r.a)(e,function(){e.removeClass("wl-tinymce-typing")},3e3)})})},36:function(t,e,n){"use strict";var r=function(t,e){for(var n=arguments.length,r=Array(n>3?n-3:0),o=3;o<n;o++)r[o-3]=arguments[o];var i=arguments.length>2&&void 0!==arguments[2]?arguments[2]:500;clearTimeout(t.data("timeout")),t.data("timeout",setTimeout.apply(void 0,[e,i].concat(r)))};e.a=r}});
//# sourceMappingURL=wordlift-admin-tinymce.bundle.js.map