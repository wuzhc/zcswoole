DROP TABLE IF EXISTS contrab;
CREATE TABLE contrab (
  `id`          INT(11)                 NOT NULL AUTO_INCREMENT,
  `timer`       VARCHAR(255)
                CHARACTER SET utf8
                COLLATE utf8_general_ci NOT NULL
  COMMENT '定时周期',
  `command`     VARCHAR(255)
                CHARACTER SET utf8
                COLLATE utf8_general_ci NOT NULL
  COMMENT '命令',
  `remark`      TEXT CHARACTER SET utf8
                COLLATE utf8_general_ci NOT NULL
  COMMENT '备注',
  `create_time` DATETIME                         DEFAULT NULL,
  `status`      TINYINT(2)              NOT NULL DEFAULT '0'
  COMMENT '0开启,1关闭',
  PRIMARY KEY (`id`)
)
  ENGINE = InnoDB
  CHARSET = utf8
  COLLATE utf8_general_ci
  COMMENT = '定时器';



DROP TABLE IF EXISTS chat_friends_map;
CREATE TABLE chat_friends_map (
  `id`        INT(11)    NOT NULL AUTO_INCREMENT,
  `user_id`   INT(11)    NOT NULL
  COMMENT '本人ID',
  `friend_id` INT(11)    NOT NULL
  COMMENT '好友ID',
  `status`    TINYINT(2) NOT NULL DEFAULT '0'
  COMMENT '0申请状态,1好友状态,2加入黑名单,3已删除',
  PRIMARY KEY (`id`)
)
  ENGINE = InnoDB
  CHARSET = utf8
  COLLATE utf8_general_ci
  COMMENT = '好友关系表';
INSERT INTO chat_friends_map (user_id, friend_id, status) VALUES (1, 2, 1), (1, 3, 1), (2, 1, 1), (3, 1, 1);



DROP TABLE IF EXISTS chat_groups_map;
CREATE TABLE chat_groups_map (
  `id`       INT(11)     NOT NULL AUTO_INCREMENT,
  `user_id`  INT(11)     NOT NULL
  COMMENT '本人ID',
  `group_id` INT(11)     NOT NULL
  COMMENT '群组ID',
  `status`   TINYINT(11) NOT NULL DEFAULT '0'
  COMMENT '0申请状态,1已加入,2加入黑名单,3已删除',
  PRIMARY KEY (`id`)
)
  ENGINE = InnoDB
  CHARSET = utf8
  COLLATE utf8_general_ci
  COMMENT = '群组关系表';
INSERT INTO chat_groups_map (user_id, group_id, status) VALUES (1, 1, 1), (1, 2, 1);



DROP TABLE IF EXISTS chat_group;
CREATE TABLE chat_group (
  `id`       INT(11)                 NOT NULL AUTO_INCREMENT,
  `name`     VARCHAR(255)
             CHARACTER SET utf8
             COLLATE utf8_general_ci NOT NULL
  COMMENT '群组名称',
  `portrait` VARCHAR(255)
             CHARACTER SET utf8
             COLLATE utf8_general_ci          DEFAULT ''
  COMMENT '头像',
  `status`   TINYINT(11)             NOT NULL DEFAULT '0'
  COMMENT '0正常,1删除',
  PRIMARY KEY (`id`)
)
  ENGINE = InnoDB
  CHARSET = utf8
  COLLATE utf8_general_ci
  COMMENT = '群组表';
INSERT INTO chat_group (name, portrait, status)
VALUES ('zcswoole交流群', '/chat/img/20170926103645_27.jpg', 0), ('nodejs交流群', '/chat/img/20170926103645_58.jpg', 0)