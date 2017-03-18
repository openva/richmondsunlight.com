SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

CREATE TABLE IF NOT EXISTS `bills` (
  `id` mediumint(8) unsigned NOT NULL,
  `number` varchar(7) COLLATE utf8_bin NOT NULL,
  `session_id` tinyint(3) unsigned NOT NULL,
  `chamber` enum('senate','house') CHARACTER SET latin1 NOT NULL DEFAULT 'house',
  `catch_line` varchar(255) COLLATE utf8_bin DEFAULT NULL,
  `chief_patron_id` smallint(5) unsigned NOT NULL,
  `summary` text COLLATE utf8_bin,
  `full_text` mediumtext COLLATE utf8_bin,
  `impact_statement_id` smallint(5) unsigned DEFAULT NULL,
  `last_committee_id` tinyint(3) unsigned DEFAULT NULL,
  `current_chamber` enum('house','senate') CHARACTER SET latin1 DEFAULT NULL,
  `status` varchar(60) CHARACTER SET latin1 DEFAULT NULL,
  `outcome` enum('passed','failed') CHARACTER SET latin1 DEFAULT NULL,
  `view_count` smallint(5) unsigned NOT NULL DEFAULT '0',
  `identical` varchar(120) CHARACTER SET utf8 DEFAULT NULL,
  `notes` text COLLATE utf8_bin NOT NULL,
  `summary_hash` char(32) CHARACTER SET latin1 DEFAULT NULL,
  `interestingness` smallint(5) unsigned DEFAULT NULL COMMENT 'How much interest there was in this bill throughout the session.',
  `hotness` smallint(5) unsigned DEFAULT NULL COMMENT 'For ranking bills by popularity.',
  `copatrons` tinyint(3) unsigned DEFAULT NULL COMMENT 'Number',
  `incorporated_into` mediumint(8) unsigned DEFAULT NULL COMMENT 'The ID of the bill.',
  `dls_prepared` tinyint(1) DEFAULT NULL,
  `date_introduced` date DEFAULT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `date_created` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `bills_copatrons` (
  `id` mediumint(8) unsigned NOT NULL,
  `bill_id` mediumint(8) unsigned NOT NULL,
  `legislator_id` smallint(5) unsigned NOT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `bills_full_text` (
  `id` mediumint(8) unsigned NOT NULL,
  `bill_id` mediumint(8) unsigned NOT NULL,
  `number` varchar(10) CHARACTER SET latin1 NOT NULL,
  `date_introduced` date NOT NULL,
  `text` mediumtext COLLATE utf8_bin,
  `failed_retrievals` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of times we''ve queried this text.',
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `bills_places` (
  `id` int(10) unsigned NOT NULL,
  `bill_id` mediumint(8) unsigned NOT NULL,
  `placename` varchar(128) COLLATE utf8_bin NOT NULL,
  `latitude` float NOT NULL,
  `longitude` float NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `bills_section_numbers` (
  `id` int(10) unsigned NOT NULL,
  `full_text_id` mediumint(9) NOT NULL,
  `bill_id` mediumint(8) unsigned NOT NULL,
  `section_number` varchar(16) COLLATE utf8_bin NOT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `bills_status` (
  `id` int(10) unsigned NOT NULL,
  `bill_id` mediumint(8) unsigned NOT NULL,
  `session_id` tinyint(3) unsigned NOT NULL,
  `status` varchar(255) CHARACTER SET latin1 NOT NULL,
  `translation` varchar(120) CHARACTER SET latin1 DEFAULT NULL,
  `date` date NOT NULL,
  `lis_vote_id` varchar(10) CHARACTER SET latin1 DEFAULT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_created` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `bills_views` (
  `id` mediumint(8) unsigned NOT NULL,
  `bill_id` mediumint(8) NOT NULL,
  `user_id` int(5) DEFAULT NULL,
  `ip` varchar(19) CHARACTER SET latin1 NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `blacklist` (
  `id` smallint(5) unsigned NOT NULL,
  `ip` varchar(19) CHARACTER SET latin1 NOT NULL,
  `user_id` int(5) unsigned DEFAULT NULL,
  `email` varchar(30) CHARACTER SET latin1 DEFAULT NULL,
  `score` smallint(5) unsigned NOT NULL,
  `reason` text CHARACTER SET latin1,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `chamber_status` (
  `id` mediumint(8) unsigned NOT NULL,
  `date` datetime NOT NULL,
  `chamber` enum('house','senate') COLLATE utf8_bin NOT NULL,
  `session_id` tinyint(3) unsigned NOT NULL,
  `text` mediumtext COLLATE utf8_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `comments` (
  `id` mediumint(8) unsigned NOT NULL,
  `user_id` int(5) unsigned NOT NULL,
  `bill_id` mediumint(8) unsigned NOT NULL,
  `name` varchar(60) CHARACTER SET latin1 DEFAULT NULL,
  `email` varchar(60) CHARACTER SET latin1 DEFAULT NULL,
  `url` varchar(60) CHARACTER SET latin1 DEFAULT NULL,
  `ip` varchar(19) CHARACTER SET latin1 DEFAULT NULL,
  `comment` text COLLATE utf8_bin,
  `type` enum('comment','pingback') CHARACTER SET latin1 NOT NULL DEFAULT 'comment',
  `status` enum('published','spam','awaiting moderation','deleted') CHARACTER SET latin1 DEFAULT NULL,
  `editors_pick` enum('y','n') COLLATE utf8_bin NOT NULL DEFAULT 'n',
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `comments_subscriptions` (
  `id` mediumint(8) unsigned NOT NULL,
  `user_id` int(5) unsigned NOT NULL,
  `bill_id` mediumint(8) unsigned NOT NULL,
  `hash` char(8) COLLATE utf8_bin NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_created` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `committees` (
  `id` tinyint(4) unsigned NOT NULL,
  `lis_id` tinyint(32) unsigned DEFAULT NULL,
  `parent_id` tinyint(3) unsigned DEFAULT NULL,
  `name` varchar(120) CHARACTER SET latin1 NOT NULL,
  `shortname` varchar(32) CHARACTER SET latin1 DEFAULT NULL,
  `chamber` enum('house','senate') CHARACTER SET latin1 NOT NULL,
  `meeting_time` varchar(120) COLLATE utf8_bin DEFAULT NULL,
  `url` varchar(120) CHARACTER SET latin1 DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `committee_members` (
  `id` smallint(5) unsigned NOT NULL,
  `committee_id` tinyint(3) unsigned NOT NULL,
  `representative_id` smallint(5) unsigned NOT NULL,
  `position` enum('chair','vice chair') CHARACTER SET latin1 DEFAULT NULL,
  `date_started` date NOT NULL,
  `date_ended` date DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `dashboard_bills` (
  `id` mediumint(8) unsigned NOT NULL,
  `user_id` int(5) unsigned NOT NULL,
  `bill_id` mediumint(8) unsigned NOT NULL,
  `portfolio_id` mediumint(8) unsigned NOT NULL,
  `notes` text COLLATE utf8_bin,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `dashboard_portfolios` (
  `id` mediumint(8) unsigned NOT NULL,
  `user_id` int(5) unsigned NOT NULL,
  `name` varchar(120) CHARACTER SET latin1 NOT NULL,
  `hash` char(5) CHARACTER SET latin1 NOT NULL,
  `notes` text CHARACTER SET latin1,
  `notify` enum('hourly','daily','none') CHARACTER SET latin1 NOT NULL DEFAULT 'none',
  `public` enum('y','n') CHARACTER SET latin1 NOT NULL DEFAULT 'n',
  `watch_list_id` mediumint(8) unsigned DEFAULT NULL,
  `view_count` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `dashboard_user_data` (
  `user_id` int(8) unsigned NOT NULL,
  `organization` varchar(128) CHARACTER SET latin1 DEFAULT NULL,
  `email_active` enum('y','n') CHARACTER SET latin1 NOT NULL DEFAULT 'y',
  `type` enum('paid','free') CHARACTER SET latin1 NOT NULL DEFAULT 'free',
  `last_access` datetime NOT NULL,
  `expires` date DEFAULT NULL,
  `unsub_hash` char(8) CHARACTER SET latin1 NOT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `dashboard_watch_lists` (
  `id` mediumint(8) unsigned NOT NULL,
  `user_id` int(5) unsigned NOT NULL,
  `tag` varchar(30) CHARACTER SET latin1 DEFAULT NULL,
  `patron_id` smallint(6) DEFAULT NULL,
  `committee_id` tinyint(3) unsigned DEFAULT NULL,
  `keyword` varchar(120) CHARACTER SET latin1 DEFAULT NULL,
  `status` enum('introduced','passed house','passed senate','passed','failed','continued','approved','vetoed') CHARACTER SET latin1 DEFAULT NULL,
  `current_chamber` enum('house','senate') CHARACTER SET latin1 DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `districts` (
  `id` int(10) unsigned NOT NULL,
  `chamber` enum('house','senate') CHARACTER SET latin1 NOT NULL,
  `number` tinyint(3) unsigned NOT NULL,
  `date_started` date NOT NULL,
  `date_ended` date NOT NULL,
  `description` varchar(300) CHARACTER SET latin1 DEFAULT NULL,
  `notes` text CHARACTER SET latin1,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_created` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `dockets` (
  `id` mediumint(8) unsigned NOT NULL,
  `date` date NOT NULL,
  `committee_id` tinyint(3) unsigned NOT NULL,
  `bill_id` mediumint(8) unsigned NOT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `files` (
  `id` smallint(5) unsigned NOT NULL,
  `chamber` enum('house','senate') CHARACTER SET utf8 DEFAULT NULL,
  `committee_id` smallint(5) unsigned DEFAULT NULL,
  `author_name` varchar(60) CHARACTER SET utf8 DEFAULT NULL,
  `title` varchar(60) CHARACTER SET utf8 DEFAULT NULL,
  `html` text CHARACTER SET utf8,
  `path` varchar(128) CHARACTER SET utf8 DEFAULT NULL,
  `description` text CHARACTER SET utf8 NOT NULL,
  `license` varchar(60) CHARACTER SET utf8 NOT NULL,
  `type` enum('video','audio') CHARACTER SET utf8 NOT NULL,
  `length` time DEFAULT NULL,
  `fps` decimal(4,2) unsigned DEFAULT NULL,
  `capture_rate` tinyint(3) unsigned DEFAULT NULL,
  `capture_directory` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
  `width` smallint(5) unsigned DEFAULT NULL COMMENT 'Video width',
  `height` smallint(5) unsigned DEFAULT NULL COMMENT 'Video height',
  `date` date NOT NULL,
  `sponsor` text COLLATE utf8_bin,
  `youtube_id` char(11) COLLATE utf8_bin DEFAULT NULL,
  `srt` mediumtext CHARACTER SET utf8,
  `transcript` text CHARACTER SET utf8,
  `video_index_cache` blob,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `gazetteer` (
  `id` mediumint(8) unsigned NOT NULL,
  `name` varchar(128) CHARACTER SET utf8 NOT NULL,
  `municipality` varchar(64) CHARACTER SET utf8 NOT NULL,
  `latitude` float NOT NULL,
  `longitude` float NOT NULL,
  `elevation` tinyint(3) unsigned DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `meetings` (
  `id` mediumint(8) unsigned NOT NULL,
  `committee_id` smallint(5) unsigned DEFAULT NULL,
  `date` date NOT NULL,
  `time` time DEFAULT NULL,
  `timedesc` varchar(120) COLLATE utf8_bin DEFAULT NULL COMMENT 'A description in lieu of a time',
  `description` mediumtext COLLATE utf8_bin NOT NULL,
  `location` varchar(120) COLLATE utf8_bin NOT NULL,
  `session_id` tinyint(3) unsigned NOT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `minutes` (
  `id` smallint(5) unsigned NOT NULL,
  `date` date NOT NULL COMMENT 'dat',
  `chamber` enum('house','senate') CHARACTER SET latin1 NOT NULL,
  `text` text CHARACTER SET latin1 NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `polls` (
  `id` mediumint(8) unsigned NOT NULL,
  `bill_id` smallint(5) unsigned NOT NULL,
  `vote` enum('y','n') COLLATE utf8_bin NOT NULL,
  `user_id` int(5) unsigned DEFAULT NULL,
  `ip` varchar(19) COLLATE utf8_bin NOT NULL,
  `date_created` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `representatives` (
  `id` smallint(5) unsigned NOT NULL,
  `name_formal` varchar(32) CHARACTER SET latin1 NOT NULL,
  `name` varchar(32) CHARACTER SET latin1 NOT NULL,
  `name_formatted` varchar(64) COLLATE utf8_bin NOT NULL COMMENT 'i.e. Del. Jon Doe (R-1)',
  `shortname` varchar(16) CHARACTER SET latin1 NOT NULL,
  `lis_shortname` varchar(30) CHARACTER SET latin1 NOT NULL,
  `chamber` enum('house','senate') CHARACTER SET latin1 NOT NULL,
  `district_id` smallint(5) unsigned NOT NULL,
  `date_started` date NOT NULL,
  `date_ended` date DEFAULT NULL,
  `party` enum('D','R','I') CHARACTER SET latin1 NOT NULL DEFAULT 'D',
  `bio` text CHARACTER SET latin1,
  `birthday` date DEFAULT NULL,
  `race` enum('american indian','asian','pacific islander','white','black') CHARACTER SET latin1 NOT NULL DEFAULT 'white',
  `sex` enum('male','female') CHARACTER SET latin1 NOT NULL DEFAULT 'male',
  `notes` text CHARACTER SET latin1,
  `phone_district` varchar(12) CHARACTER SET latin1 DEFAULT NULL,
  `phone_richmond` varchar(12) CHARACTER SET latin1 DEFAULT NULL,
  `address_district` varchar(80) CHARACTER SET latin1 DEFAULT NULL,
  `address_richmond` varchar(80) CHARACTER SET latin1 DEFAULT NULL,
  `email` varchar(64) CHARACTER SET latin1 DEFAULT NULL,
  `url` varchar(64) CHARACTER SET latin1 DEFAULT NULL,
  `rss_url` varchar(64) CHARACTER SET latin1 DEFAULT NULL,
  `twitter_rss_url` varchar(96) CHARACTER SET latin1 DEFAULT NULL,
  `sbe_id` varchar(11) COLLATE utf8_bin DEFAULT NULL,
  `lis_id` smallint(5) unsigned DEFAULT NULL,
  `place` varchar(60) CHARACTER SET latin1 DEFAULT NULL COMMENT 'District office location',
  `latitude` float DEFAULT NULL,
  `longitude` float DEFAULT NULL,
  `contributions` mediumtext COLLATE utf8_bin COMMENT 'A Serialized Array',
  `partisanship` tinyint(3) unsigned DEFAULT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `date_created` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `representatives_districts` (
  `id` smallint(5) unsigned NOT NULL,
  `representative_id` smallint(5) unsigned NOT NULL,
  `district_id` smallint(5) unsigned NOT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `representatives_fundraising` (
  `id` smallint(5) unsigned NOT NULL,
  `representative_id` smallint(5) unsigned NOT NULL,
  `year` year(4) NOT NULL,
  `total` mediumint(8) unsigned NOT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `representatives_terms` (
  `id` smallint(5) unsigned NOT NULL,
  `representative_id` smallint(5) unsigned NOT NULL,
  `chamber` enum('house','senate') COLLATE utf8_bin NOT NULL,
  `date_start` date NOT NULL,
  `date_end` date NOT NULL,
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `representatives_votes` (
  `id` mediumint(8) unsigned NOT NULL,
  `representative_id` int(10) unsigned NOT NULL,
  `vote_id` mediumint(8) unsigned NOT NULL,
  `vote` enum('Y','N','X','A') CHARACTER SET latin1 NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `date_created` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` tinyint(3) unsigned NOT NULL,
  `lis_id` varchar(3) CHARACTER SET latin1 DEFAULT NULL,
  `year` int(10) unsigned NOT NULL,
  `suffix` varchar(30) CHARACTER SET latin1 DEFAULT NULL,
  `date_started` date NOT NULL,
  `date_ended` date DEFAULT NULL,
  `notes` text CHARACTER SET latin1 NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_created` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `tags` (
  `id` mediumint(8) unsigned NOT NULL,
  `user_id` int(5) unsigned NOT NULL,
  `bill_id` mediumint(8) unsigned NOT NULL,
  `tag` varchar(30) CHARACTER SET latin1 NOT NULL,
  `ip` varchar(19) CHARACTER SET latin1 DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(5) unsigned NOT NULL,
  `cookie_hash` char(32) CHARACTER SET latin1 NOT NULL,
  `name` varchar(60) CHARACTER SET latin1 DEFAULT NULL,
  `password` varchar(60) CHARACTER SET latin1 DEFAULT NULL,
  `email` varchar(60) CHARACTER SET latin1 DEFAULT NULL,
  `url` varchar(60) CHARACTER SET latin1 DEFAULT NULL,
  `zip` char(10) CHARACTER SET latin1 DEFAULT NULL,
  `city` varchar(30) CHARACTER SET latin1 DEFAULT NULL,
  `state` char(2) CHARACTER SET latin1 DEFAULT NULL,
  `house_district_id` smallint(5) unsigned DEFAULT NULL,
  `senate_district_id` smallint(5) unsigned DEFAULT NULL,
  `representative_id` smallint(5) unsigned DEFAULT NULL COMMENT 'If this user is a legislator',
  `trusted` enum('y','n') CHARACTER SET latin1 NOT NULL DEFAULT 'n',
  `mailing_list` enum('y','n') CHARACTER SET latin1 NOT NULL DEFAULT 'n',
  `ip` varchar(19) CHARACTER SET latin1 NOT NULL,
  `notes` text CHARACTER SET latin1,
  `private_hash` char(8) CHARACTER SET latin1 DEFAULT NULL,
  `latitude` float DEFAULT NULL,
  `longitude` float DEFAULT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_created` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `vacode` (
  `title_number` varchar(16) COLLATE utf8_bin DEFAULT NULL,
  `title_name` varchar(128) COLLATE utf8_bin DEFAULT NULL,
  `chapter_number` varchar(16) COLLATE utf8_bin DEFAULT NULL,
  `chapter_name` varchar(128) COLLATE utf8_bin DEFAULT NULL,
  `section_number` varchar(16) COLLATE utf8_bin NOT NULL,
  `section_name` varchar(255) COLLATE utf8_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `video_clips` (
  `id` mediumint(8) unsigned NOT NULL,
  `legislator_id` smallint(5) unsigned DEFAULT NULL,
  `bill_id` mediumint(8) unsigned DEFAULT NULL,
  `file_id` smallint(5) unsigned NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `screenshot` varchar(128) COLLATE utf8_bin NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_created` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `video_index` (
  `id` int(10) unsigned NOT NULL,
  `file_id` smallint(5) unsigned NOT NULL,
  `time` time NOT NULL,
  `screenshot` varchar(30) COLLATE utf8_bin NOT NULL,
  `raw_text` varchar(100) COLLATE utf8_bin NOT NULL,
  `type` enum('bill','legislator') COLLATE utf8_bin NOT NULL,
  `linked_id` int(10) unsigned DEFAULT NULL,
  `ignored` enum('y','n') COLLATE utf8_bin NOT NULL DEFAULT 'n',
  `face_json` mediumtext COLLATE utf8_bin,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `video_index_faces` (
  `id` int(10) unsigned NOT NULL,
  `video_index_id` int(10) unsigned NOT NULL,
  `legislator_id` smallint(5) unsigned NOT NULL,
  `confidence` decimal(2,0) unsigned NOT NULL,
  `width` decimal(2,0) NOT NULL,
  `height` decimal(2,0) NOT NULL,
  `center` varchar(11) COLLATE utf8_bin NOT NULL,
  `mood` varchar(9) COLLATE utf8_bin DEFAULT NULL,
  `mood_confidence` decimal(2,0) DEFAULT NULL,
  `smiling` enum('true','false') COLLATE utf8_bin DEFAULT NULL,
  `smiling_confidence` decimal(2,0) DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `video_transcript` (
  `id` int(10) unsigned NOT NULL,
  `file_id` smallint(5) unsigned NOT NULL,
  `text` tinytext COLLATE utf8_bin NOT NULL,
  `time_start` time(2) NOT NULL,
  `time_end` time(2) NOT NULL,
  `new_speaker` enum('y','n') COLLATE utf8_bin NOT NULL DEFAULT 'n',
  `legislator_id` smallint(5) unsigned DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE IF NOT EXISTS `votes` (
  `id` int(10) unsigned NOT NULL,
  `lis_id` varchar(12) CHARACTER SET latin1 DEFAULT NULL,
  `bill_id` int(10) unsigned DEFAULT NULL,
  `chamber` enum('house','senate') CHARACTER SET latin1 NOT NULL,
  `session_id` tinyint(3) unsigned NOT NULL,
  `committee_id` int(10) unsigned DEFAULT NULL,
  `outcome` enum('pass','fail') CHARACTER SET latin1 DEFAULT NULL,
  `tally` varchar(8) CHARACTER SET latin1 NOT NULL,
  `contested` float(3,2) unsigned DEFAULT NULL COMMENT 'How contested that vote was',
  `total` tinyint(3) unsigned DEFAULT NULL,
  `partisanship` float(5,4) unsigned DEFAULT NULL,
  `notes` text CHARACTER SET latin1,
  `date` date DEFAULT NULL,
  `date_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_created` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;


ALTER TABLE `bills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bills` (`number`,`session_id`),
  ADD KEY `number` (`number`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `chief_patron_id` (`chief_patron_id`),
  ADD KEY `summary_hash` (`summary_hash`),
  ADD KEY `view_count` (`view_count`),
  ADD KEY `hotness` (`hotness`),
  ADD KEY `outcome` (`outcome`),
  ADD KEY `incorporated_into` (`incorporated_into`),
  ADD KEY `copatrons` (`copatrons`),
  ADD KEY `duplicates` (`session_id`,`summary_hash`,`id`),
  ADD KEY `interestingness` (`interestingness`),
  ADD KEY `dls_prepared` (`dls_prepared`);

ALTER TABLE `bills_copatrons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bill_legislator` (`bill_id`,`legislator_id`),
  ADD KEY `bill_id` (`bill_id`),
  ADD KEY `legislator_id` (`legislator_id`);

ALTER TABLE `bills_full_text`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bill_id_2` (`bill_id`,`number`),
  ADD KEY `bill_id` (`bill_id`),
  ADD FULLTEXT KEY `text` (`text`);

ALTER TABLE `bills_places`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bill_id` (`bill_id`,`latitude`,`longitude`);

ALTER TABLE `bills_section_numbers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bill_id` (`bill_id`,`section_number`),
  ADD KEY `full_text_id` (`full_text_id`);

ALTER TABLE `bills_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `combo` (`bill_id`,`session_id`,`status`,`date`),
  ADD KEY `lis_vote_id` (`lis_vote_id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `date` (`date`),
  ADD KEY `bill_id` (`bill_id`),
  ADD FULLTEXT KEY `status` (`status`);

ALTER TABLE `bills_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bill_id` (`bill_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `date` (`date`);

ALTER TABLE `blacklist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`);

ALTER TABLE `chamber_status`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bill_id` (`bill_id`),
  ADD KEY `editors_pick` (`editors_pick`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`),
  ADD KEY `publishable` (`bill_id`,`status`);

ALTER TABLE `comments_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`,`bill_id`,`hash`);

ALTER TABLE `committees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chamber` (`chamber`),
  ADD KEY `shortname` (`shortname`),
  ADD KEY `lis_id` (`lis_id`),
  ADD KEY `parent_id` (`parent_id`);

ALTER TABLE `committee_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assignments` (`committee_id`,`representative_id`);

ALTER TABLE `dashboard_bills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bill_portfolio` (`bill_id`,`portfolio_id`),
  ADD KEY `user_id` (`user_id`,`bill_id`),
  ADD KEY `portfoilo_id` (`portfolio_id`),
  ADD FULLTEXT KEY `notes` (`notes`);

ALTER TABLE `dashboard_portfolios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `hash` (`hash`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `notify` (`notify`),
  ADD KEY `watch_list_id` (`watch_list_id`),
  ADD KEY `public` (`public`),
  ADD FULLTEXT KEY `notes` (`notes`);

ALTER TABLE `dashboard_user_data`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `organization_id` (`organization`,`email_active`,`expires`);

ALTER TABLE `dashboard_watch_lists`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `districts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chamber` (`chamber`,`number`);

ALTER TABLE `dockets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `date` (`date`,`committee_id`,`bill_id`),
  ADD KEY `bill_id` (`bill_id`,`date`);

ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chamber` (`chamber`),
  ADD FULLTEXT KEY `description` (`description`);

ALTER TABLE `gazetteer`
  ADD PRIMARY KEY (`id`),
  ADD KEY `county` (`municipality`,`latitude`,`longitude`);

ALTER TABLE `meetings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `triumverate` (`date`,`time`,`committee_id`);

ALTER TABLE `minutes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `date` (`date`,`chamber`);

ALTER TABLE `polls`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bill_id_4` (`bill_id`,`ip`),
  ADD UNIQUE KEY `one_vote` (`bill_id`,`user_id`),
  ADD KEY `bill_id` (`bill_id`);

ALTER TABLE `representatives`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shortname` (`shortname`),
  ADD KEY `name` (`name_formal`),
  ADD KEY `lis_shortname` (`lis_shortname`),
  ADD KEY `lis_id` (`lis_id`),
  ADD KEY `latitude` (`latitude`,`longitude`),
  ADD KEY `place` (`place`),
  ADD KEY `partisanship` (`partisanship`),
  ADD FULLTEXT KEY `name_2` (`name_formal`,`bio`,`notes`);

ALTER TABLE `representatives_districts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `representative_id` (`representative_id`,`district_id`);

ALTER TABLE `representatives_fundraising`
  ADD PRIMARY KEY (`id`),
  ADD KEY `who_when` (`representative_id`,`year`);

ALTER TABLE `representatives_terms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chamber_list` (`chamber`,`date_end`),
  ADD KEY `representative_id` (`representative_id`);

ALTER TABLE `representatives_votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rep_id` (`representative_id`,`vote_id`),
  ADD KEY `representative_id` (`representative_id`),
  ADD KEY `vote_id` (`vote_id`);

ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `year` (`year`,`suffix`),
  ADD KEY `lis_id` (`lis_id`),
  ADD FULLTEXT KEY `notes` (`notes`);

ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_pairing` (`bill_id`,`tag`),
  ADD KEY `bill_id` (`bill_id`),
  ADD KEY `tag` (`tag`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cookie_hash` (`cookie_hash`);

ALTER TABLE `vacode`
  ADD UNIQUE KEY `section_number` (`section_number`);

ALTER TABLE `video_clips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `legislator_id` (`legislator_id`,`bill_id`,`file_id`),
  ADD KEY `file_id` (`file_id`);

ALTER TABLE `video_index`
  ADD PRIMARY KEY (`id`),
  ADD KEY `Distinct` (`file_id`,`screenshot`,`type`),
  ADD KEY `Player` (`type`,`linked_id`),
  ADD KEY `subquery` (`file_id`,`linked_id`),
  ADD KEY `ignore` (`ignored`);

ALTER TABLE `video_index_faces`
  ADD PRIMARY KEY (`id`),
  ADD KEY `video_index_id` (`video_index_id`,`legislator_id`);

ALTER TABLE `video_transcript`
  ADD PRIMARY KEY (`id`),
  ADD KEY `file_id` (`file_id`,`time_start`,`time_end`),
  ADD KEY `speaker` (`legislator_id`);

ALTER TABLE `votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `main` (`session_id`,`lis_id`),
  ADD KEY `bill_id` (`bill_id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `date` (`date`),
  ADD KEY `contested` (`contested`),
  ADD KEY `lis_id` (`lis_id`);


ALTER TABLE `bills`
  MODIFY `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `bills_copatrons`
  MODIFY `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `bills_full_text`
  MODIFY `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `bills_places`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `bills_section_numbers`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `bills_status`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `bills_views`
  MODIFY `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `blacklist`
  MODIFY `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `chamber_status`
  MODIFY `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `comments`
  MODIFY `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `comments_subscriptions`
  MODIFY `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `committees`
  MODIFY `id` tinyint(4) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `committee_members`
  MODIFY `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `dashboard_bills`
  MODIFY `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `dashboard_portfolios`
  MODIFY `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `dashboard_watch_lists`
  MODIFY `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `districts`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `dockets`
  MODIFY `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `files`
  MODIFY `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `meetings`
  MODIFY `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `minutes`
  MODIFY `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `polls`
  MODIFY `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `representatives`
  MODIFY `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `representatives_districts`
  MODIFY `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `representatives_fundraising`
  MODIFY `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `representatives_terms`
  MODIFY `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `representatives_votes`
  MODIFY `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `sessions`
  MODIFY `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `tags`
  MODIFY `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `users`
  MODIFY `id` int(5) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `video_clips`
  MODIFY `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `video_index`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `video_index_faces`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `video_transcript`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `votes`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;

ALTER TABLE `video_clips`
  ADD CONSTRAINT `video_clips_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `video_clips_ibfk_2` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `video_clips_ibfk_3` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `video_clips_ibfk_4` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `video_clips_ibfk_5` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

ALTER TABLE `video_index`
  ADD CONSTRAINT `video_index_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `video_index_ibfk_2` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
