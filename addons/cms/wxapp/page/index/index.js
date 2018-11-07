const { Tab } = require('../../assets/libs/zanui/index');

var app = getApp();
Page(Object.assign({}, Tab, {

  data: {
    imgUrls: [
      '/assets/images/avatar.png',
      '/assets/images/avatar.png',
      '/assets/images/avatar.png',
      '/assets/images/avatar.png'
    ],
    swiperIndex:'index',

    city:1

  },
  channel: 0,
  page: 1,
  onLoad: function () {
    var that = this;
    this.channel = 0;
    this.page = 1;
    this.setData({ ["tab.list"]: app.globalData.indexTabList });
    app.request('/index/index', {
      // city:1
    }, function (data, ret) {
      console.log(data);
     
    }, function (data, ret) {
      app.error(ret.msg);
    });


    // app.request('/index/getInformation', {
    //   city:this.data.city
    // }, function (data, ret) {
    //   console.log(data);

    // }, function (data, ret) {
    //   app.error(ret.msg);
    // });

  },
  onPullDownRefresh: function () {
    this.setData({ nodata: false, nomore: false });
    this.page = 1;
    this.loadArchives(function () {
      wx.stopPullDownRefresh();
    });
  },
  onReachBottom: function () {
    var that = this;
    this.loadArchives(function (data) {
      if (data.archivesList.length == 0) {
        app.info("暂无更多数据");
      }
    });
  },
  loadArchives: function (cb) {
    var that = this;
    if (that.data.nomore == true || that.data.loading == true) {
      return;
    }
    this.setData({ loading: true });
    app.request('/archives/index', { channel: this.channel, page: this.page }, function (data, ret) {
      that.setData({
        loading: false,
        nodata: that.page == 1 && data.archivesList.length == 0 ? true : false,
        nomore: that.page > 1 && data.archivesList.length == 0 ? true : false,
        archivesList: that.page > 1 ? that.data.archivesList.concat(data.archivesList) : data.archivesList,
      });
      that.page++;
      typeof cb == 'function' && cb(data);
    }, function (data, ret) {
      app.error(ret.msg);
    });
  },

  handleZanTabChange(e) {
    var componentId = e.componentId;
    var selectedId = e.selectedId;
    this.channel = selectedId;
    this.page = 1;
    this.setData({
      nodata: false,
      nomore: false,
      [`${componentId}.selectedId`]: selectedId
    });
    wx.pageScrollTo({ scrollTop: 0 });
    this.loadArchives();
  },
  onShareAppMessage: function () {
    return {
      title: 'FastAdmin',
      desc: '基于ThinkPHP5和Bootstrap的极速后台框架',
      path: '/page/index/index'
    }
  },
  pickerChange(e) {
    const index = e.detail.value
    const value = this.data.items[index]
    const classNames = `wux-animate--${value}`

    this.setData({
      index,
      'example.classNames': classNames,
    })
  },
  switchChange(e) {
    const { model } = e.currentTarget.dataset

    this.setData({
      [model]: e.detail.value,
    })
  },
  onClick() { console.log('onClick') },
  onEnter(e) { console.log('onEnter', e.detail) },
  onEntering(e) { console.log('onEntering', e.detail) },
  onEntered(e) { console.log('onEntered', e.detail) },
  onExit() { console.log('onExit') },
  onExiting() { console.log('onExiting') },
  onExited() { console.log('onExited') },
  onToggle() {
    this.setData({
      show: !this.data.show,
    })
  },

  onChange(e) {
    const { animateStatus } = e.detail

    switch (animateStatus) {
      case 'entering':
        this.setData({ status: 'Entering…' })
        break
      case 'entered':
        this.setData({ status: 'Entered!' })
        break
      case 'exiting':
        this.setData({ status: 'Exiting…' })
        break
      case 'exited':
        this.setData({ status: 'Exited!' })
        break
    }
  },
  onShow: function () {
    var that = this;
    app.request('/text/index', {}, function (data, ret) {
      that.setData({
        text: data.plan,
      });
      console.log(data.plan);
    }, function (data, ret) {
      app.error(ret.msg);
    });
  },
}))












