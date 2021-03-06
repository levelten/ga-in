(function(a) {
  if (typeof define === "function" && define.amd) {
    define(["jquery"], a)
  } else {
    if (typeof module === "object" && module.exports) {
      module.exports = a(require("jquery"))
    } else {
      a(jQuery)
    }
  }
}(function(g) {
  var f = {
    minHeight: 0,
    elements: [],
    percentage: true,
    userTiming: true,
    pixelDepth: true,
    nonInteraction: true,
    gaGlobal: false,
    gtmOverride: false,
    trackerName: false,
    dataLayer: "dataLayer"
  };
  var b = g(window),
      a = [],
      h = false,
      d = 0,
      c, j, e, k, i;
  g.scrollDepth = function(u) {
    var m = +new Date;
    u = g.extend({}, f, u);
    if (g(document).height() < u.minHeight) {
      return
    }
    if (u.gaGlobal) {
      c = true;
      e = u.gaGlobal
    } else {
      if (typeof gtag === "function") {
        k = true;
        e = "gtag"
      } else {
        if (typeof ga === "function") {
          c = true;
          e = "ga"
        } else {
          if (typeof __gaTracker === "function") {
            c = true;
            e = "__gaTracker"
          }
        }
      }
    }
    if (typeof _gaq !== "undefined" && typeof _gaq.push === "function") {
      j = true
    }
    if (typeof u.eventHandler === "function") {
      i = u.eventHandler
    } else {
      if (typeof window[u.dataLayer] !== "undefined" && typeof window[u.dataLayer].push === "function" && !u.gtmOverride) {
        i = function(v) {
          window[u.dataLayer].push(v)
        }
      }
    }

    function s(y, w, v, x) {
      var z = u.trackerName ? (u.trackerName + ".send") : "send";
      if (i) {
        i({
          event: "ScrollDistance",
          eventCategory: "Scroll Depth",
          eventAction: y,
          eventLabel: w,
          eventValue: 1,
          eventNonInteraction: u.nonInteraction
        });
        if (u.pixelDepth && arguments.length > 2 && v > d) {
          d = v;
          i({
            event: "ScrollDistance",
            eventCategory: "Scroll Depth",
            eventAction: "Pixel Depth",
            eventLabel: n(v),
            eventValue: 1,
            eventNonInteraction: u.nonInteraction
          })
        }
        if (u.userTiming && arguments.length > 3) {
          i({
            event: "ScrollTiming",
            eventCategory: "Scroll Depth",
            eventAction: y,
            eventLabel: w,
            eventTiming: x
          })
        }
      } else {
        if (k) {
          window[e]("event", y, {
            event_category: "Scroll Depth",
            event_label: w,
            non_interaction: u.nonInteraction
          })
        }
        if (c) {
          window[e](z, "event", "Scroll Depth", y, w, 1, {
            nonInteraction: u.nonInteraction
          });
          if (u.pixelDepth && arguments.length > 2 && v > d) {
            d = v;
            window[e](z, "event", "Scroll Depth", "Pixel Depth", n(v), 1, {
              nonInteraction: u.nonInteraction
            })
          }
          if (u.userTiming && arguments.length > 3) {
            window[e](z, "timing", "Scroll Depth", y, x, w)
          }
        }
        if (j) {
          _gaq.push(["_trackEvent", "Scroll Depth", y, w, 1, u.nonInteraction]);
          if (u.pixelDepth && arguments.length > 2 && v > d) {
            d = v;
            _gaq.push(["_trackEvent", "Scroll Depth", "Pixel Depth", n(v), 1, u.nonInteraction])
          }
          if (u.userTiming && arguments.length > 3) {
            _gaq.push(["_trackTiming", "Scroll Depth", y, x, w, 100])
          }
        }
      }
    }

    function r(v) {
      return {
        "25%": parseInt(v * 0.25, 10),
        "50%": parseInt(v * 0.5, 10),
        "75%": parseInt(v * 0.75, 10),
        "100%": v - 5
      }
    }

    function p(w, v, x) {
      g.each(w, function(y, z) {
        if (g.inArray(y, a) === -1 && v >= z) {
          s("Percentage", y, v, x);
          a.push(y)
        }
      })
    }

    function o(x, v, w) {
      g.each(x, function(y, z) {
        if (g.inArray(z, a) === -1 && g(z).length) {
          if (v >= g(z).offset().top) {
            s("Elements", z, v, w);
            a.push(z)
          }
        }
      })
    }

    function n(v) {
      return (Math.floor(v / 250) * 250).toString()
    }

    function t() {
      l()
    }
    g.scrollDepth.reset = function() {
      a = [];
      d = 0;
      b.off("scroll.scrollDepth");
      l()
    };
    g.scrollDepth.addElements = function(v) {
      if (typeof v == "undefined" || !g.isArray(v)) {
        return
      }
      g.merge(u.elements, v);
      if (!h) {
        l()
      }
    };
    g.scrollDepth.removeElements = function(v) {
      if (typeof v == "undefined" || !g.isArray(v)) {
        return
      }
      g.each(v, function(x, z) {
        var w = g.inArray(z, u.elements);
        var y = g.inArray(z, a);
        if (w != -1) {
          u.elements.splice(w, 1)
        }
        if (y != -1) {
          a.splice(y, 1)
        }
      })
    };

    function q(A, C) {
      var y, x, v;
      var B = null;
      var z = 0;
      var w = function() {
        z = new Date;
        B = null;
        v = A.apply(y, x)
      };
      return function() {
        var D = new Date;
        if (!z) {
          z = D
        }
        var E = C - (D - z);
        y = this;
        x = arguments;
        if (E <= 0) {
          clearTimeout(B);
          B = null;
          z = D;
          v = A.apply(y, x)
        } else {
          if (!B) {
            B = setTimeout(w, E)
          }
        }
        return v
      }
    }

    function l() {
      h = true;
      b.on("scroll.scrollDepth", q(function() {
        var x = g(document).height(),
            w = window.innerHeight ? window.innerHeight : b.height(),
            v = b.scrollTop() + w,
            y = r(x),
            z = +new Date - m;
        if (a.length >= u.elements.length + (u.percentage ? 4 : 0)) {
          b.off("scroll.scrollDepth");
          h = false;
          return
        }
        if (u.elements) {
          o(u.elements, v, z)
        }
        if (u.percentage) {
          p(y, v, z)
        }
      }, 500))
    }
    t()
  };
  return g.scrollDepth
}));