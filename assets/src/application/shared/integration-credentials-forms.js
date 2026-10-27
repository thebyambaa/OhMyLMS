// Reconstructed application fragment. Assembled in manifest order within factory 1841.
var j7 = {
    zoom: function (e) {
      var t = e.onSave,
        n = e.onCancel,
        r = Y6((0, g.useState)({
          account_id: "",
          client_id: "",
          client_secret: "",
          webhook_secret_token: ""
        }), 2),
        a = r[0],
        o = r[1],
        i = Y6((0, g.useState)(""), 2),
        c = i[0],
        u = i[1],
        s = Y6((0, g.useState)(!1), 2),
        d = s[0],
        m = s[1],
        p = Y6((0, g.useState)({}), 2),
        f = p[0],
        v = p[1],
        h = Y6((0, g.useState)(!0), 2),
        y = h[0],
        _ = h[1];
      (0, g.useEffect)(function () {
        var e = !0;
        function t() {
          return (t = q6(H6().m(function t() {
            var n;
            return H6().w(function (t) {
              for (;;) switch (t.p = t.n) {
                case 0:
                  return t.p = 0, t.n = 1, l()({
                    path: "/ohmylms/v1/zoom/settings/credentials",
                    method: "GET"
                  });
                case 1:
                  n = t.v, e && null != n && n.data && (o({
                    account_id: n.data.account_id || "",
                    client_id: n.data.client_id || "",
                    client_secret: n.data.client_secret || "",
                    webhook_secret_token: n.data.webhook_secret_token || ""
                  }), u(n.data.webhook_url || "")), t.n = 3;
                  break;
                case 2:
                  t.p = 2, t.v;
                case 3:
                  return t.p = 3, e && _(!1), t.f(3);
                case 4:
                  return t.a(2);
              }
            }, t, null, [[0, 2, 3, 4]]);
          }))).apply(this, arguments);
        }
        return function () {
          t.apply(this, arguments);
        }(), function () {
          e = !1;
        };
      }, []);
      var w = function () {
          var e = arguments.length > 0 && void 0 !== arguments[0] ? arguments[0] : a,
            t = {};
          return e.account_id && e.account_id.trim() || (t.account_id = (0, b.__)("Account ID is required", "ohmylms")), e.client_id && e.client_id.trim() || (t.client_id = (0, b.__)("Client ID is required", "ohmylms")), e.client_secret && e.client_secret.trim() || (t.client_secret = (0, b.__)("Client Secret is required", "ohmylms")), t;
        },
        E = function () {
          var e = q6(H6().m(function e() {
            var n, r, o;
            return H6().w(function (e) {
              for (;;) switch (e.p = e.n) {
                case 0:
                  if (n = w(), v(n), !(Object.keys(n).length > 0)) {
                    e.n = 1;
                    break;
                  }
                  return e.a(2);
                case 1:
                  return m(!0), e.p = 2, e.n = 3, l()({
                    path: "/ohmylms/v1/zoom/settings/credentials",
                    method: "POST",
                    data: a
                  });
                case 3:
                  null != (r = e.v) && r.success && t && t("success", (0, b.__)("Zoom credentials saved successfully.", "ohmylms")), e.n = 5;
                  break;
                case 4:
                  e.p = 4, o = e.v, t && t("error", (0, b.__)("Something went wrong.", "ohmylms")), console.error(o);
                case 5:
                  return e.p = 5, m(!1), e.f(5);
                case 6:
                  return e.a(2);
              }
            }, e, null, [[2, 4, 5, 6]]);
          }));
          return function () {
            return e.apply(this, arguments);
          };
        }(),
        S = function (e, t) {
          o(function (n) {
            return L6(L6({}, n), {}, V6({}, e, t));
          }), v(function (t) {
            return L6(L6({}, t), {}, V6({}, e, void 0));
          });
        };
      return y ? React.createElement("div", null, (0, b.__)("Loading Zoom settings...", "ohmylms")) : React.createElement(React.Fragment, null, React.createElement(I.CardWP, {
        variant: "secondary",
        isBorderless: !0,
        style: {
          minHeight: "280px"
        }
      }, React.createElement(I.SpacerWP, {
        marginBottom: 5,
        marginTop: 5,
        padding: 1
      }, React.createElement(Pf, {
        title: (0, b.__)("Account ID", "ohmylms"),
        inputType: "text",
        value: a.account_id,
        onChange: function (e) {
          return S("account_id", e);
        },
        placeholder: (0, b.__)("Enter your Zoom Account ID", "ohmylms"),
        spacerMarginBottom: 0,
        error: f.account_id
      }), React.createElement(Pf, {
        title: (0, b.__)("Client ID", "ohmylms"),
        inputType: "text",
        value: a.client_id,
        onChange: function (e) {
          return S("client_id", e);
        },
        placeholder: (0, b.__)("Enter your Zoom Client ID", "ohmylms"),
        spacerMarginBottom: 0,
        error: f.client_id
      }), React.createElement(Pf, {
        title: (0, b.__)("Client Secret", "ohmylms"),
        inputType: "textarea",
        value: a.client_secret,
        onChange: function (e) {
          return S("client_secret", e);
        },
        placeholder: (0, b.__)("Enter your Zoom Client Secret", "ohmylms"),
        spacerMarginBottom: 0,
        error: f.client_secret
      }), c && React.createElement(Pf, {
        title: (0, b.__)("Webhook URL", "ohmylms"),
        inputType: "text",
        value: c,
        readOnly: !0,
        spacerMarginBottom: 0,
        description: (0, b.__)('Paste this into your Zoom app\'s Event Subscriptions endpoint URL, subscribed to the "Recording Completed" event.', "ohmylms")
      }), React.createElement(Pf, {
        title: (0, b.__)("Webhook Secret Token", "ohmylms"),
        inputType: "text",
        value: a.webhook_secret_token,
        onChange: function (e) {
          return S("webhook_secret_token", e);
        },
        placeholder: (0, b.__)("Enter the Secret Token from your Zoom app's Event Subscriptions page", "ohmylms"),
        spacerMarginBottom: 0,
        description: (0, b.__)("Required for auto-attaching cloud recordings — without it, recordings must be added by hand.", "ohmylms")
      }))), React.createElement(I.FlexWP, {
        justify: "flex-end",
        gap: 2
      }, React.createElement(I.ButtonWP, {
        isSecondary: !0,
        onClick: n
      }, (0, b.__)("Cancel", "ohmylms")), React.createElement(I.ButtonWP, {
        isPrimary: !0,
        onClick: E,
        disabled: d
      }, d ? (0, b.__)("Saving...", "ohmylms") : (0, b.__)("Save", "ohmylms"))));
    },
    googlemeet: function (e) {
      var t = e.onSave,
        n = e.onCancel,
        r = r7((0, g.useState)({
          client_id: "",
          client_secret: "",
          redirect_url: ""
        }), 2),
        a = r[0],
        o = r[1],
        i = r7((0, g.useState)(!1), 2),
        c = i[0],
        u = i[1],
        s = r7((0, g.useState)({}), 2),
        d = s[0],
        m = s[1],
        p = r7((0, g.useState)(!0), 2),
        f = p[0],
        v = p[1],
        h = r7((0, g.useState)(!1), 2),
        y = h[0],
        _ = h[1],
        w = r7((0, g.useState)(!1), 2),
        E = w[0],
        S = w[1],
        R = r7((0, g.useState)("unknown"), 2),
        x = R[0],
        C = R[1];
      (0, g.useEffect)(function () {
        var e = !0;
        function t() {
          return (t = n7(X6().m(function t() {
            var n, r;
            return X6().w(function (t) {
              for (;;) switch (t.p = t.n) {
                case 0:
                  return t.p = 0, t.n = 1, l()({
                    path: "/ohmylms/v1/googlemeet/settings/credentials",
                    method: "GET"
                  });
                case 1:
                  n = t.v, e && null != n && n.data && (o({
                    client_id: n.data.client_id || "",
                    client_secret: n.data.client_secret || "",
                    redirect_url: n.data.redirect_url || ""
                  }), null != n && null !== (r = n.data) && void 0 !== r && null !== (r = r.tokens) && void 0 !== r && r.access_token && C("valid")), t.n = 3;
                  break;
                case 2:
                  t.p = 2, t.v;
                case 3:
                  return t.p = 3, e && v(!1), t.f(3);
                case 4:
                  return t.a(2);
              }
            }, t, null, [[0, 2, 3, 4]]);
          }))).apply(this, arguments);
        }
        return function () {
          t.apply(this, arguments);
        }(), function () {
          e = !1;
        };
      }, []);
      var P = function () {
          var e = arguments.length > 0 && void 0 !== arguments[0] ? arguments[0] : a,
            t = {};
          return e.client_id && e.client_id.trim() || (t.client_id = (0, b.__)("Client ID is required", "ohmylms")), e.client_secret && e.client_secret.trim() || (t.client_secret = (0, b.__)("Client Secret is required", "ohmylms")), t;
        },
        O = function () {
          var e = n7(X6().m(function e() {
            var n, r, o;
            return X6().w(function (e) {
              for (;;) switch (e.p = e.n) {
                case 0:
                  if (n = P(), m(n), !(Object.keys(n).length > 0)) {
                    e.n = 1;
                    break;
                  }
                  return e.a(2);
                case 1:
                  return u(!0), e.p = 2, e.n = 3, l()({
                    path: "/ohmylms/v1/googlemeet/settings/credentials",
                    method: "POST",
                    data: a
                  });
                case 3:
                  null != (r = e.v) && r.success && (_(!0), t && t("success", (0, b.__)("Google Meet credentials saved successfully. Please authenticate with Google.", "ohmylms"))), e.n = 5;
                  break;
                case 4:
                  e.p = 4, o = e.v, t && t("error", (0, b.__)("Something went wrong.", "ohmylms")), console.error(o);
                case 5:
                  return e.p = 5, u(!1), e.f(5);
                case 6:
                  return e.a(2);
              }
            }, e, null, [[2, 4, 5, 6]]);
          }));
          return function () {
            return e.apply(this, arguments);
          };
        }(),
        k = function () {
          var e = n7(X6().m(function e() {
            var n, r;
            return X6().w(function (e) {
              for (;;) switch (e.p = e.n) {
                case 0:
                  return S(!0), e.p = 1, e.n = 2, l()({
                    path: "/ohmylms/v1/googlemeet/settings/oauth-url",
                    method: "GET"
                  });
                case 2:
                  if (null == (n = e.v) || !n.success || !n.auth_url) {
                    e.n = 3;
                    break;
                  }
                  window.location.href = n.auth_url, e.n = 4;
                  break;
                case 3:
                  throw new Error((null == n ? void 0 : n.message) || "OAuth URL not found");
                case 4:
                  e.n = 6;
                  break;
                case 5:
                  e.p = 5, r = e.v, console.error("OAuth authentication failed:", r), t && t("error", (0, b.__)("Failed to start authentication.", "ohmylms"));
                case 6:
                  return e.p = 6, S(!1), e.f(6);
                case 7:
                  return e.a(2);
              }
            }, e, null, [[1, 5, 6, 7]]);
          }));
          return function () {
            return e.apply(this, arguments);
          };
        }(),
        j = function (e, t) {
          o(function (n) {
            return K6(K6({}, n), {}, J6({}, e, t));
          }), m(function (t) {
            return K6(K6({}, t), {}, J6({}, e, void 0));
          });
        };
      return f ? React.createElement("div", null, (0, b.__)("Loading Google Meet settings...", "ohmylms")) : React.createElement(React.Fragment, null, React.createElement(I.CardWP, {
        variant: "secondary",
        isBorderless: !0,
        style: {
          minHeight: "220px"
        }
      }, React.createElement(I.SpacerWP, {
        marginBottom: 5,
        marginTop: 5,
        padding: 1
      }, React.createElement(Pf, {
        title: (0, b.__)("Client ID", "ohmylms"),
        inputType: "text",
        value: a.client_id,
        onChange: function (e) {
          return j("client_id", e);
        },
        placeholder: (0, b.__)("Enter your Google Client ID", "ohmylms"),
        spacerMarginBottom: 0,
        error: d.client_id
      }), React.createElement(Pf, {
        title: (0, b.__)("Client Secret", "ohmylms"),
        inputType: "text",
        value: a.client_secret,
        onChange: function (e) {
          return j("client_secret", e);
        },
        placeholder: (0, b.__)("Enter your Google Client Secret", "ohmylms"),
        spacerMarginBottom: 0,
        error: d.client_secret
      }), React.createElement(Pf, {
        title: (0, b.__)("Redirect URL", "ohmylms"),
        inputType: "text",
        value: a.redirect_url,
        onChange: function (e) {
          return j("redirect_url", e);
        },
        placeholder: (0, b.__)("Enter your Google Redirect URL", "ohmylms"),
        spacerMarginBottom: 0,
        error: d.redirect_url
      }))), React.createElement(I.FlexWP, {
        justify: "flex-end",
        gap: 2
      }, React.createElement(I.ButtonWP, {
        isSecondary: !0,
        onClick: n
      }, (0, b.__)("Cancel", "ohmylms")), React.createElement(I.ButtonWP, {
        isPrimary: !0,
        onClick: O,
        disabled: c
      }, c ? (0, b.__)("Saving...", "ohmylms") : (0, b.__)("Save", "ohmylms")), "valid" === x && !y && React.createElement(I.ButtonWP, {
        isPrimary: !0,
        disabled: !0
      }, (0, b.__)("Google Meet Authenticated", "ohmylms")), y && React.createElement(I.ButtonWP, {
        isPrimary: !0,
        onClick: k,
        disabled: E
      }, E ? (0, b.__)("Redirecting...", "ohmylms") : (0, b.__)("Authenticate with Google", "ohmylms")), "error" === x && React.createElement(I.ButtonWP, {
        isPrimary: !0,
        disabled: !0
      }, (0, b.__)("Error checking authentication", "ohmylms"))));
    },
    google_signin: function (e) {
      var t = e.onSave,
        n = e.onCancel,
        r = S7((0, g.useState)({
          client_id: "",
          client_secret: ""
        }), 2),
        a = r[0],
        o = r[1],
        i = S7((0, g.useState)(!1), 2),
        c = i[0],
        u = i[1],
        s = S7((0, g.useState)(""), 2),
        d = s[0],
        m = s[1],
        p = S7((0, g.useState)(!1), 2),
        f = p[0],
        v = p[1],
        h = S7((0, g.useState)({}), 2),
        y = h[0],
        _ = h[1],
        w = S7((0, g.useState)(!0), 2),
        E = w[0],
        S = w[1];
      (0, g.useEffect)(function () {
        var e = !0;
        function t() {
          return (t = E7(g7().m(function t() {
            var n;
            return g7().w(function (t) {
              for (;;) switch (t.p = t.n) {
                case 0:
                  return t.p = 0, t.n = 1, l()({
                    path: "/ohmylms/v1/auth/google/settings"
                  });
                case 1:
                  n = t.v, e && n && (o(function (e) {
                    return b7(b7({}, e), {}, {
                      client_id: n.client_id || ""
                    });
                  }), u(!!n.has_client_secret), m(n.redirect_uri || "")), t.n = 3;
                  break;
                case 2:
                  t.p = 2, t.v;
                case 3:
                  return t.p = 3, e && S(!1), t.f(3);
                case 4:
                  return t.a(2);
              }
            }, t, null, [[0, 2, 3, 4]]);
          }))).apply(this, arguments);
        }
        return function () {
          t.apply(this, arguments);
        }(), function () {
          e = !1;
        };
      }, []);
      var R = function () {
          var e = {};
          return a.client_id && a.client_id.trim() || (e.client_id = (0, b.__)("Client ID is required", "ohmylms")), c || a.client_secret && a.client_secret.trim() || (e.client_secret = (0, b.__)("Client Secret is required", "ohmylms")), e;
        },
        x = function (e, t) {
          o(function (n) {
            return b7(b7({}, n), {}, _7({}, e, t));
          }), _(function (t) {
            return b7(b7({}, t), {}, _7({}, e, void 0));
          });
        },
        C = function () {
          var e = E7(g7().m(function e() {
            var n, r, i;
            return g7().w(function (e) {
              for (;;) switch (e.p = e.n) {
                case 0:
                  if (n = R(), _(n), !(Object.keys(n).length > 0)) {
                    e.n = 1;
                    break;
                  }
                  return e.a(2);
                case 1:
                  return v(!0), e.p = 2, e.n = 3, l()({
                    path: "/ohmylms/v1/auth/google/settings",
                    method: "POST",
                    data: {
                      client_id: a.client_id,
                      client_secret: a.client_secret
                    }
                  });
                case 3:
                  r = e.v, u(!(null == r || !r.has_client_secret)), m((null == r ? void 0 : r.redirect_uri) || d), o(function (e) {
                    return b7(b7({}, e), {}, {
                      client_secret: ""
                    });
                  }), t && t("success", (0, b.__)("Google Sign-In settings saved.", "ohmylms")), e.n = 5;
                  break;
                case 4:
                  e.p = 4, i = e.v, t && t("error", (null == i ? void 0 : i.message) || (0, b.__)("Failed to save Google Sign-In settings.", "ohmylms"));
                case 5:
                  return e.p = 5, v(!1), e.f(5);
                case 6:
                  return e.a(2);
              }
            }, e, null, [[2, 4, 5, 6]]);
          }));
          return function () {
            return e.apply(this, arguments);
          };
        }();
      return E ? React.createElement("div", null, (0, b.__)("Loading Google Sign-In settings...", "ohmylms")) : React.createElement(React.Fragment, null, React.createElement(I.CardWP, {
        variant: "secondary",
        isBorderless: !0,
        style: {
          minHeight: "220px"
        }
      }, React.createElement(I.SpacerWP, {
        marginBottom: 5,
        marginTop: 5,
        padding: 1
      }, React.createElement(Pf, {
        title: (0, b.__)("Client ID", "ohmylms"),
        inputType: "text",
        value: a.client_id,
        onChange: function (e) {
          return x("client_id", e);
        },
        placeholder: (0, b.__)("Enter your Google Client ID", "ohmylms"),
        spacerMarginBottom: 0,
        error: y.client_id
      }), React.createElement(Pf, {
        title: (0, b.__)("Client Secret", "ohmylms"),
        inputType: "text",
        value: a.client_secret,
        onChange: function (e) {
          return x("client_secret", e);
        },
        placeholder: c ? (0, b.__)("Leave blank to keep the current secret", "ohmylms") : (0, b.__)("Enter your Google Client Secret", "ohmylms"),
        spacerMarginBottom: 0,
        error: y.client_secret
      }), React.createElement(I.SpacerWP, {
        padding: 4,
        marginBottom: 0
      }, React.createElement(I.TextWP, {
        as: "p",
        size: "14px",
        weight: 600,
        color: "#000D25"
      }, (0, b.__)("Authorized redirect URI", "ohmylms")), React.createElement(I.SpacerWP, {
        marginBottom: 2
      }), React.createElement(I.FlexWP, {
        gap: 2,
        align: "center"
      }, React.createElement(I.FlexItemWP, {
        isBlock: !0
      }, React.createElement(I.InputWP, {
        type: "text",
        value: d,
        readOnly: !0
      })), React.createElement(I.CopyToClipboard, {
        textToCopy: d
      })), React.createElement(I.SpacerWP, {
        marginBottom: 2
      }), React.createElement(I.TextWP, {
        as: "p",
        size: "13px",
        color: "#687784"
      }, (0, b.__)("Paste this into your Google Cloud OAuth client's Authorized redirect URIs.", "ohmylms"))))), React.createElement(I.FlexWP, {
        justify: "flex-end",
        gap: 2
      }, React.createElement(I.ButtonWP, {
        isSecondary: !0,
        onClick: n
      }, (0, b.__)("Cancel", "ohmylms")), React.createElement(I.ButtonWP, {
        isPrimary: !0,
        onClick: C,
        disabled: f
      }, f ? (0, b.__)("Saving...", "ohmylms") : (0, b.__)("Save", "ohmylms"))));
    },
    microsoft_signin: function () {
      // Replaced by MicrosoftSignInSettings at build time (see components.json).
      return null;
    }
  },
  A7 = function (e) {
    var t = e.integration,
      n = e.onSave,
      r = e.onCancel,
      a = function (e, t) {
        return function (e) {
          if (Array.isArray(e)) return e;
        }(e) || function (e, t) {
          var n = null == e ? null : "undefined" != typeof Symbol && e[Symbol.iterator] || e["@@iterator"];
          if (null != n) {
            var r,
              a,
              o,
              i,
              l = [],
              c = !0,
              u = !1;
            try {
              if (o = (n = n.call(e)).next, 0 === t) {
                if (Object(n) !== n) return;
                c = !1;
              } else for (; !(c = (r = o.call(n)).done) && (l.push(r.value), l.length !== t); c = !0);
            } catch (e) {
              u = !0, a = e;
            } finally {
              try {
                if (!c && null != n.return && (i = n.return(), Object(i) !== i)) return;
              } finally {
                if (u) throw a;
              }
            }
            return l;
          }
        }(e, t) || function (e, t) {
          if (e) {
            if ("string" == typeof e) return k7(e, t);
            var n = {}.toString.call(e).slice(8, -1);
            return "Object" === n && e.constructor && (n = e.constructor.name), "Map" === n || "Set" === n ? Array.from(e) : "Arguments" === n || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n) ? k7(e, t) : void 0;
          }
        }(e, t) || function () {
          throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.");
        }();
      }((0, g.useState)(t.settings || {}), 2),
      o = a[0],
      i = a[1],
      l = j7[t.key];
    return React.createElement(React.Fragment, null, React.createElement(Nr, {
      onClick: r
    }), React.createElement(I.SpacerWP, {
      marginBottom: 5
    }), React.createElement(I.FlexWP, {
      gap: 3,
      justify: "flex-start"
    }, t.icon && React.createElement("img", {
      src: t.icon,
      alt: t.label,
      style: {
        width: 50,
        height: 50,
        borderRadius: "4px",
        background: "#fff",
        border: "1px solid #eee",
        objectFit: "contain",
        padding: "4px"
      }
    }), React.createElement(I.HeadingWP, {
      level: 3,
      size: 20,
      weight: 500,
      color: "#000D25"
    }, (0, b.__)(t.label, "ohmylms"))), React.createElement(I.SpacerWP, {
      marginBottom: 2
    }), React.createElement(I.TextWP, {
      size: 14,
      variant: "muted"
    }, t.description), l ? React.createElement(l, {
      settings: o,
      onChange: function (e, t) {
        i(P7(P7({}, o), {}, O7({}, e, t)));
      },
      onSave: n,
      onCancel: r
    }) : React.createElement(I.TextWP, null, (0, b.__)("No settings available for this integration.", "ohmylms")));
  };
