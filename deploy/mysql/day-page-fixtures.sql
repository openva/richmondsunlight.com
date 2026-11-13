--
-- Hand-maintained fixtures for day pages (/day/2026-01-26/): what each chamber and committee did on
-- January 26, 2026, the day of the House floor session in video-minutes-fixtures.sql and the
-- committee meetings in video-page-fixtures.sql. The bills are those files' bills, except SJ 999,
-- which is here so that the Senate did something that day too.
--
-- House floor:      HB 176 passed (with a roll call, HV0126), HB 341 failed on a close vote, and
--                   HB 38 was read a second time. HB 414's fiscal impact statement is paperwork,
--                   which days leave out.
-- Senate floor:     SJ 999 agreed to.
-- Appropriations:   HB 199 reported. Met at 9 a.m.
-- Capital Outlay:   Its docket has HB 206 and HB 210. It recommended reporting HB 206 ("Subcommittee
--                   recommends", which doesn't name the subcommittee: the docket does), and did
--                   nothing with HB 210. Met at 7:30 a.m.
-- Rules:            HB 288 continued to 2027, by voice vote.
-- A party caucus, which days leave out.
-- A meeting on January 14, 2099, so that the most recent day always has something coming up.
--
-- Rows use INSERT IGNORE so that existing local rows are kept.
--

INSERT IGNORE INTO `bills`
    (`id`, `number`, `session_id`, `chamber`, `catch_line`, `chief_patron_id`, `current_chamber`,
     `status`, `date_introduced`, `date_created`)
VALUES
(995001,'sj999',32,'senate','Commending the day page fixtures.',NULL,'senate','passed senate','2026-01-14','2026-01-26 00:00:00');

INSERT IGNORE INTO `bills_status`
    (`id`, `bill_id`, `session_id`, `status`, `date`, `lis_vote_id`, `chamber`, `date_created`)
VALUES
(990000001,83845,32,'Read third time and passed House (97-Y 0-N 0-A)','2026-01-26 12:41:00','HV0126','house','2026-01-26 12:41:00'),
(990000002,84141,32,'Read third time and defeated by House (45-Y 52-N 0-A)','2026-01-26 12:44:00',NULL,'house','2026-01-26 12:44:00'),
(990000003,83690,32,'Read second time','2026-01-26 12:50:00',NULL,'house','2026-01-26 12:50:00'),
(990000004,84242,32,'Fiscal Impact Statement from Department of Planning and Budget (HB414)','2026-01-26 09:00:00',NULL,'house','2026-01-26 09:00:00'),
(990000005,995001,32,'Agreed to by Senate (40-Y 0-N 0-A)','2026-01-26 12:30:00',NULL,'senate','2026-01-26 12:30:00'),
(990000006,83922,32,'Reported from Appropriations (17-Y 5-N)','2026-01-26 00:00:00',NULL,'house','2026-01-26 10:30:00'),
(990000007,83929,32,'Subcommittee recommends reporting (6-Y 2-N)','2026-01-26 00:00:00',NULL,'house','2026-01-26 08:30:00'),
(990000008,84017,32,'Continued to 2027 in Rules by voice vote','2026-01-26 00:00:00',NULL,'house','2026-01-26 16:00:00');

INSERT IGNORE INTO `votes`
    (`id`, `lis_id`, `bill_id`, `chamber`, `session_id`, `outcome`, `tally`, `total`, `date`, `date_created`)
VALUES
(995001,'HV0126',83845,'house',32,'pass','97-0',97,'2026-01-26','2026-01-26 12:41:00');

INSERT IGNORE INTO `dockets` (`id`, `date`, `committee_id`, `bill_id`, `date_created`) VALUES
(9900001,'2026-01-26',112,83929,'2026-01-23 12:00:00'),
(9900002,'2026-01-26',112,83934,'2026-01-23 12:00:00');

INSERT IGNORE INTO `meetings`
    (`id`, `committee_id`, `date`, `time`, `timedesc`, `description`, `location`, `session_id`, `date_created`)
VALUES
(995001,2,'2026-01-26','09:00:00',NULL,'House Appropriations Committee','House Appropriations Room, Room 1200, General Assembly Building',32,'2026-01-23 12:00:00'),
(995002,2,'2026-01-26','07:30:00',NULL,'House Appropriations-Capital Outlay Subcommittee','House Appropriations Room, Room 1200, General Assembly Building',32,'2026-01-23 12:00:00'),
(995003,NULL,'2026-01-26','11:00:00',NULL,'House Democratic Caucus Caucus','House Room 2, The Capitol',32,'2026-01-23 12:00:00'),
(995004,NULL,'2099-01-14','10:00:00',NULL,'Joint Subcommittee on Day Page Fixtures Other','Senate Room A, General Assembly Building',32,'2026-01-23 12:00:00');
