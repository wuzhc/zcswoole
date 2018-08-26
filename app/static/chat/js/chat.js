$(function () {
    // 当前登录用户ID
    var userID = $('#user-id').data('userid');

    // 第一个聊天消息记录
    (function () {
        var dialogID = $('#user-box').data('dialogid');
        if (dialogID) {
            getChatRecord(dialogID);
        }
    })();

    var ws = new WebSocket('ws://127.0.0.1:9502');
    ws.onopen = function () {
        console.log('connect success');
        var userID = $('#user-id').data('userid');
        ws.send(JSON.stringify({from: userID, opType: 'open'}));
    };

    // 接受消息
    // 是不是当前窗口,当前窗口则追加聊天消息,不是的话则只在对话显示对话消息
    // 是不是没有打开窗口,没有的话生成一个窗口,只在对话显示对话消息
    ws.onmessage = function (evt) {
        var data = JSON.parse(evt.data);
        console.log(data);

        if (data.opType === 'message') {
            // 当前聊天对话
            var currentDialogType = $('.chat-dialog>ul>li.bg').data('type');
            var currentDialogSelector = $('.chat-dialog>ul>li.bg').attr('id');
            var flag = false;
            if (currentDialogType == data.targetType) {
                if (currentDialogType == 1 && (currentDialogSelector == 'user-'+data.from)) {
                    flag = true;
                } else if (currentDialogType == 2 && (currentDialogSelector == 'group-'+data.groupID)) {
                    flag = true;
                }
            }

            if (flag === true) {
                var answer = '<li>' +
                    '<div class="answerHead"><img src="' + data.portrait + '"/></div>' +
                    '<div class="answers"><img class="jiao" src="/chat/img/jiao.jpg">' + data.content + '</div>' +
                    '</li>';
                $('.newsList').append(answer);
            } else {
                var to,selectorID,name,portrait;
                if (data.targetType == 1) { // 对象是人
                    to = data.from;
                    selectorID = 'user-' + to;
                    name = data.name;
                    portrait = data.portrait;
                } else { // 对象是群
                    to = data.groupID;
                    selectorID = 'group-' + to;
                    name = data.groupName;
                    portrait = data.groupPortrait;
                }

                var hasDialog = false;
                $('.chat-dialog').find('li').each(function () {
                    if (to == $(this).data('to') && data.targetType == $(this).data('type')) {
                        hasDialog = true;
                        return true;
                    }
                });

                // 没有对话框,添加一个
                if (!hasDialog) {
                    var dialog = '<li id="' + selectorID + '" data-dialogid="' + data.dialogID + '" data-to="' + to + '" data-type="' + data.targetType + '">' +
                        '<div class="liLeft">' +
                        '<img src="' + portrait + '"/>' +
                        '<i class="tip"></i>'+
                        '</div>' +
                        '<div class="liRight">' +
                        '<span class="intername">' + name + '</span>' +
                        '<span class="infor">' + data.content + '</span>' +
                        '</div>' +
                        '</li>';

                    $('.chat-dialog').find('ul').prepend(dialog);
                    return true;
                }
            }

            if (data.targetType == 1) { // 私聊
                $('#user-' + data.from).find('.infor').text(data.content);
                $('#user-' + data.from).attr('data-dialogID', data.dialogID);
                $('#user-' + data.from).find('.liLeft').children('i').addClass('tip');
            } else {                    // 群聊
                $('#group-' + data.groupID).find('.infor').text(data.content);
                $('#group-' + data.groupID).find('.liLeft').children('i').addClass('tip');
                $('#group-' + data.groupID).attr('data-dialogID', data.dialogID);
            }

            $('.RightCont').scrollTop($('.RightCont')[0].scrollHeight);
        }
    };

    ws.onclose = function () {
        console.log('connect close');
    };

    // 发送
    $('.sendBtn').on('click', function () {
        var news = $('#dope').val();
        if (news == '') {
            alert('不能为空');
        } else {
            var obj = $('.conLeft').find('li.bg');
            var data = {
                from: $('#user-id').data('userid'),
                to: obj.data('to'), // 个人或群组
                content: news,
                dialogID: obj.data('dialogid'),
                opType: 'message',
                targetType: obj.data('type')
            };
            ws.send(JSON.stringify(data));
            $('#dope').val('');
            var str = '';
            var headerImg = $('#user-id').data('userimg');
            str += '<li>' +
                '<div class="nesHead"><img src="' + headerImg + '"/></div>' +
                '<div class="news"><img class="jiao" src="/chat/img/jiao.jpg">' + news + '</div>' +
                '</li>';

            $('.newsList').append(str);
            // setTimeout(answers, 1000);
            $('.conLeft').find('li.bg').children('.liRight').children('.infor').text(news);
            $('.RightCont').scrollTop($('.RightCont')[0].scrollHeight);
        }
    });

    // 回车监控是否发送
    $('#dope').on('keypress', function (e) {
        if (e.keyCode === 13) {
            $('.sendBtn').click();
        }
    });

    // 回复
    function answers() {
        var arr = ["你好", "今天天气很棒啊", "你吃饭了吗？", "我最美我最美", "我是可爱的僵小鱼", "你们忍心这样子对我吗？", "spring天下无敌，实习工资850", "我不管，我最帅，我是你们的小可爱", "段友出征，寸草不生", "一入段子深似海，从此节操是路人", "馒头：嗷", "突然想开个车", "段子界混的最惨的两个狗：拉斯，普拉达。。。"];
        var aa = Math.floor((Math.random() * arr.length));
        var answer = '';
        answer += '<li>' +
            '<div class="answerHead"><img src="/chat/img/tou.jpg"/></div>' +
            '<div class="answers"><img class="jiao" src="/chat/img/jiao.jpg">' + arr[aa] + '</div>' +
            '</li>';
        $('.newsList').append(answer);
        $('.RightCont').scrollTop($('.RightCont')[0].scrollHeight);
    }

    $('.ExP').on('mouseenter', function () {
        $('.emjon').show();
    });

    $('.emjon').on('mouseleave', function () {
        $('.emjon').hide();
    });

    // 表情发送
    $('.emjon li').on('click', function () {
        var imgSrc = $(this).children('img').attr('src');
        var obj = $('.conLeft').find('li.bg');
        var data = {
            from: $('#user-id').data('userid'),
            to: obj.data('to'), // 个人或群组
            content: imgSrc,
            dialogID: obj.data('dialogid'),
            opType: 'message',
            targetType: obj.data('type')
        };

        ws.send(JSON.stringify(data));
        var str = "";
        str += '<li>' +
            '<div class="nesHead"><img src="/chat/img/6.jpg"/></div>' +
            '<div class="news"><img class="jiao" src="/chat/img/jiao.jpg"><img class="Expr" src="' + imgSrc + '"></div>' +
            '</li>';
        $('.newsList').append(str);
        $('.emjon').hide();
        $('.RightCont').scrollTop($('.RightCont')[0].scrollHeight);
    });

    // 获取聊天记录
    function getChatRecord(dialogID) {
        $.ajax({
            url: '/backend/chat/getChatRecords?dialogID=' + dialogID,
            type: 'get',
            dataType: 'json',
            success: function (data) {
                var data = data.data || [];
                for (var i = 0, len = data.length; i < len; i++) {
                    if (data[i].user_id == userID) {       // 本人消息
                        var str = '<li>' +
                            '<div class="nesHead"><img src="' + data[i].portrait + '"/></div>' +
                            '<div class="news"><img class="jiao" src="/chat/img/jiao.jpg">' + data[i].content + '</div>' +
                            '</li>';
                        $('.newsList').append(str);
                    } else {                            // 对方消息
                        var answer = '<li>' +
                            '<div class="answerHead"><img src="' + data[i].portrait + '"/></div>' +
                            '<div class="answers"><img class="jiao" src="/chat/img/20170926103645_03_02.jpg">' + data[i].content + '</div>' +
                            '</li>';
                        $('.newsList').append(answer);
                    }
                }
                $('.RightCont').scrollTop($('.RightCont')[0].scrollHeight);
            }
        })
    }

    // 标记已读
    function updateMsgStatus(dialogID) {
        $.ajax({
            url: '/backend/chat/updateMsgStatus?dialogID=' + dialogID,
            type: 'get',
            dataType: 'json',
            success: function (data) {
            }
        })
    }

    $('.qqBox').on('click', '.chat-tab', function () {     // 选项卡切换
        $(this).addClass('chat-tab-on').siblings().removeClass('chat-tab-on');
        var index = $(this).index();
        $('.chat-cnt').addClass('none');
        $('.chat-cnt').eq(index).removeClass('none');
    }).on('click', '.chat-target', function () {          // 点击好友或群组打开对话框
        var portrait = $(this).find('img').attr('src');
        var name = $(this).find('.intername').text();
        var dialogID = 0;
        var content = '...';
        var to = 0;
        var selectorID = '';

        var type = $(this).data('type');
        if (type == 1) { // 对象是人
            to = $(this).data('friendid');
            selectorID = 'user-' + to;
        } else { // 对象是群
            to = $(this).data('groupid');
            selectorID = 'group-' + to;
        }

        // 清除已有的对话框
        $('.chat-dialog').find('li').each(function () {
            if (to == $(this).data('to') && type == $(this).data('type')) {
                dialogID = $(this).data('dialogid');
                content = $(this).find('.infor').text();
                $(this).remove();
                return true;
            }
        });

        var dialog = '<li id="' + selectorID + '" data-dialogid="' + dialogID + '" data-to="' + to + '" data-type="' + type + '" class="bg">' +
            '<div class="liLeft">' +
            '<img src="' + portrait + '"/>' +
            '</div>' +
            '<div class="liRight">' +
            '<span class="intername">' + name + '</span>' +
            '<span class="infor">' + content + '</span>' +
            '</div>' +
            '</li>';

        $('.chat-dialog').find('ul').prepend(dialog);
        $('.chat-tab').removeClass('chat-tab-on').eq(0).addClass('chat-tab-on');
        $('.chat-cnt').addClass('none').eq(0).removeClass('none');

        // 触发点击
        $('.chat-dialog>ul>li:eq(0)').trigger('click');
    }).on('click', '.chat-dialog>ul>li', function () {
        $(this).addClass('bg').siblings().removeClass('bg');
        var intername = $(this).find('.intername').text();
        $('.headName').text(intername);
        $('.newsList').html('');

        // 红点提示
        $(this).children('.liLeft').children('i').removeClass('tip');

        // 发送按钮设置
        if ($('.sendBtn').hasClass('bg-gray')) {
            $('.sendBtn').removeClass('bg-gray').removeAttr('disabled');
        }

        var dialogID = $(this).data('dialogid');
        if (dialogID) {
            getChatRecord(dialogID);
            updateMsgStatus(dialogID);
        }
    })
});

