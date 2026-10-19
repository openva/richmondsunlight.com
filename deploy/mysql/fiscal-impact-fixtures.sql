--
-- Hand-maintained fixtures for bills' impact statements.
--
-- HB217 (2026) has statements from two agencies -- the Department of Planning and Budget (122)
-- and the Virginia Criminal Sentencing Commission (160) -- on six versions of the bill, taken from
-- LIS's FiscalImpactStatements.csv for the 2026 session. Planning and Budget revised its statement
-- on the introduced bill; this keeps the revision, as the scraper does.
--
-- Bill pages should show only the most recent statement from each agency: HB217ERF122 and
-- HB217H2F160. (SB738's statements, from one agency, are in test-records.sql.)
--
-- Rows use INSERT IGNORE so that existing local rows are kept.
--

INSERT IGNORE INTO `bills`
    (`id`, `number`, `session_id`, `chamber`, `catch_line`, `chief_patron_id`, `current_chamber`,
     `status`, `date_introduced`, `date_created`)
VALUES
(83944,'hb217',32,'house','Assault firearms &amp; certain ammunition feeding devices; purchase, sale, etc., prohibited, penalties.',450,'house','signed by governor','2026-01-08','2026-01-08 00:00:00');

INSERT IGNORE INTO `fiscal_impact_statements` (`id`, `bill_id`, `lis_id`, `pdf_url`, `date_created`)
VALUES
(990001,83944,'HB217F160','https://lis.blob.core.windows.net/files/1083210.PDF','2026-03-01 00:00:00'),
(990002,83944,'HB217F122','https://lis.blob.core.windows.net/files/1096308.PDF','2026-03-01 00:00:00'),
(990003,83944,'HB217H1F160','https://lis.blob.core.windows.net/files/1105654.PDF','2026-03-01 00:00:00'),
(990004,83944,'HB217H1F122','https://lis.blob.core.windows.net/files/1138719.PDF','2026-03-01 00:00:00'),
(990005,83944,'HB217S1F160','https://lis.blob.core.windows.net/files/1187107.PDF','2026-03-01 00:00:00'),
(990006,83944,'HB217S1F122','https://lis.blob.core.windows.net/files/1191716.PDF','2026-03-01 00:00:00'),
(990007,83944,'HB217S2F160','https://lis.blob.core.windows.net/files/1195986.PDF','2026-03-01 00:00:00'),
(990008,83944,'HB217S2F122','https://lis.blob.core.windows.net/files/1197424.PDF','2026-03-01 00:00:00'),
(990009,83944,'HB217ERF122','https://lis.blob.core.windows.net/files/1215058.PDF','2026-03-01 00:00:00'),
(990010,83944,'HB217ERF160','https://lis.blob.core.windows.net/files/1215570.PDF','2026-03-01 00:00:00'),
(990011,83944,'HB217H2F160','https://lis.blob.core.windows.net/files/1220077.PDF','2026-03-01 00:00:00');
