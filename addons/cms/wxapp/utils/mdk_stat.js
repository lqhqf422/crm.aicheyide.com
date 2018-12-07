var con = require("mdk_config.js"); var info = { appid: con.appid, accountid: con.accountid, pagenum: 0, vid: 0 }; var invite = {}; var N = App; App = function (r) { d(r, "onLaunch", lau); d(r, "onShow", appshow); N(r) }; var P = Page; Page = function (r) { d(r, "onLoad", loa); d(r, "onShow", pshow); d(r, "clickclose", close); P(r) }; function d(t, a, e) { if (t[a]) { var s = t[a]; t[a] = function (t) { e.call(this, t, a); s.call(this, t) } } else { t[a] = function (t) { e.call(this, t, a) } } } var req = function (data, act) { wx.request({ url: con.request + "/api/Client/xcxmonitor.ashx", data: { subinfo: encodeURIComponent(JSON.stringify(data)), act: act }, header: { "content-type": "text/plain;charset=utf-8" }, method: "GET", success: function (res) { return res }, fail: function (res) { return res } }) }; var lau = function (data) { if (data.scene == 1011 || data.scene == 1013 || data.scene == 1012) { if (data.query._m_ != undefined) { info.query = data.query._m_ } else { info.query = "" } } if (data.scene == 1047 || data.scene == 1049 || data.scene == 1048) { var str = decodeURIComponent(data.query.scene); var q = new Array(); q = str.split("&"); info.query = ""; for (var i = 0; i < q.length; i++) { var key = q[i].split("=")[0]; var value = q[i].split("=")[1]; if (key == "_m_") { info.query = value } } } var sid = ""; try { sid = wx.getStorageSync("mdk_sid"); info.sid = sid } catch (e) { sid = "" } info.scene = data.scene; info.url = data.path; info.refer = ""; info.isfirstpage = 0; var systeminfo = function () { wx.getSystemInfo({ success: function (t) { info.os = t.system; info.screenbitdepth = t.pixelRatio; info.resolution = t.screenWidth + "*" + t.screenHeight; info.useragent = t.system + ";" + t.brand + ";" + t.model + ";" + t.platform; req(info, "updatesysteminfo") }, complete: function () { } }) }; var getuserinfo = function () { wx.login({ success: function (res_login) { if (res_login.code) { info.logincode = res_login.code; wx.getUserInfo({ withCredentials: true, lang: "zh_CN", success: function (res_user) { info.encryptedData = res_user.encryptedData; info.iv = res_user.iv; info.isauth = 1;  }, fail: function (res) { info.isauth = 2; }, complete: function () { wx.request({ url: con.request + "/api/Client/xcxmonitor.ashx", data: { subinfo: encodeURIComponent(JSON.stringify(info)), act: "loadsession" }, header: { "content-type": "text/plain;charset=utf-8" }, method: "GET", success: function (res) { if (!res.data.IsError) { var Data = res.data.Data; info.vid = Data.vid; var sid = ""; try { sid = wx.getStorageSync("mdk_sid") } catch (e) { sid = "" } if (sid != Data.sid) { wx.setStorageSync("mdk_sid", Data.sid) } invite.open = Data.open; invite.imgurl = Data.imgurl; invite.enternum = Data.enternum; invite.refusenum = Data.refusenum; getsetting(); systeminfo() } } }) } }) } } }) }; var getsetting = function () { if (wx.getSetting) { wx.getSetting({ success: function (a) { if (con.location) { addressinfo(info) } } }) } }; var addressinfo = function () { wx.getLocation({ type: "wgs84", success: function (t) { info.geographic = t.longitude + "," + t.latitude; req(info, "updatelocation") }, complete: function () { } }) }; getuserinfo(); this.subinfo = info; this.invite = invite }; var appshow = function (data) { if (typeof data != "undefined") { this.subinfo.path = data.path; this.subinfo.scene = data.scene } }; var loa = function (options, a) { var par = getApp(); var page = this; par.subinfo.pagenum++; var sid = ""; try { sid = wx.getStorageSync("mdk_sid") } catch (e) { sid = "" } par.subinfo.url = page["route"]; if (par.subinfo.page_last_page) { page["lp"] = par.subinfo.page_last_page; par.subinfo.refer = page["lp"] } par.subinfo.page_last_page = page["route"]; if (sid != "" && par.subinfo.pagenum > 1 && par.subinfo.vid != 0) { var i = { accountid: par.subinfo.accountid, appid: par.subinfo.appid, vid: par.subinfo.vid, url: par.subinfo.url, refer: par.subinfo.refer, scene: par.subinfo.scene }; req(i, "trackinfo") } }; var t1 = 0; var pshow = function () { if (t1 != 0) { clearTimeout(t1) } var p = this; if (invite.open) { popinvite(p); return } t1 = setInterval(function () { if (invite.open == undefined) { return } else { clearInterval(t1); popinvite(p) } }, 200) }; var popinvite = function (p) { if (invite.open == "1") { p.setData({ isshow: "hide", inviteimage: "background-image: url('" + invite.imgurl + "');" }); setTimeout(function () { p.setData({ isshow: "show" }) }, invite.enternum * 1000) } }; var paramInfo = function () { return invite }; var t2 = 0; var close = function () { var page = this; page.setData({ isshow: "hide" }); if (t2 != 0) { clearTimeout(t2) } if (invite.refusenum > 0) { t2 = setTimeout(function () { page.setData({ isshow: "show" }) }, invite.refusenum * 1000) } };