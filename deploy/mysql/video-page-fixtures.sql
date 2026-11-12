--
-- Hand-maintained fixtures for video pages (/video/{slug}/): committee and subcommittee meetings,
-- and a video that's still being processed, all on the same day as the House floor session in
-- video-minutes-fixtures.sql (January 26, 2026), so that each page lists the others.
--
-- Their slugs are set by zz-video-slugs.sql: 60001 is "fcmk", 60002 is "fcmm", and 60003 is
-- "fcmn". (The floor session, 15141, is "b334".)
--
-- 60001  House Appropriations Committee. Playable, with a transcript still being prepared.
-- 60002  The Appropriations Committee's Capital Outlay Subcommittee. Playable.
-- 60003  House Rules Committee. Not downloaded yet, so there's nothing to play.
--
-- The video files don't exist; pages don't load them until they're played.
--
-- Rows use INSERT IGNORE so that existing local rows are kept.
--

INSERT IGNORE INTO `files`
    (`id`, `chamber`, `committee_id`, `title`, `path`, `description`, `license`, `type`, `length`,
     `width`, `height`, `date`, `date_created`)
VALUES
(60001,'house',2,'House Appropriations','https://archive.org/download/rs-test-house-appropriations-20260126/test.mp4','','public domain','video','01:10:00',854,480,'2026-01-26','2026-01-26 18:00:00'),
(60002,'house',112,'House Appropriations - Capital Outlay Subcommittee','https://archive.org/download/rs-test-house-capital-outlay-20260126/test.mp4','','public domain','video','00:45:00',854,480,'2026-01-26','2026-01-26 18:00:00'),
(60003,'house',12,'House Rules',NULL,'','public domain','video',NULL,NULL,NULL,'2026-01-26','2026-01-26 18:00:00');

INSERT IGNORE INTO `video_jobs`
    (`file_id`, `stage`, `status`, `attempts`, `claimed_at`, `date_modified`)
VALUES
(60001,'transcript','running',1,'2026-01-26 18:05:00','2026-01-26 18:05:00'),
(60001,'bills','done',1,NULL,'2026-01-26 18:05:00'),
(60003,'download','running',1,'2026-01-26 18:05:00','2026-01-26 18:05:00');
