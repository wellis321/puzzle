-- Additional sample puzzles for Week 1 (Days 2-7)
USE mystery_puzzle;

-- Day 2: The Broken Window
INSERT INTO puzzles (puzzle_date, title, difficulty, theme, case_summary, report_text) VALUES (
    DATE_ADD(CURDATE(), INTERVAL 1 DAY),
    'The Broken Window',
    'easy',
    'Home Burglary',
    'A home was burglarized while the family was on vacation. The thief claims they found the back door unlocked, but physical evidence suggests otherwise. One detail in the report reveals the truth.',
    '**Incident**: Burglary at 42 Maple Street\n\n**Timeline**:\n- Family left for vacation on Monday at 8:00 AM\n- Neighbor saw lights on Tuesday night around 9:00 PM\n- Police called Wednesday at 2:00 PM when family returned\n\n**Suspect Statement** (Chris Martinez, arrested nearby):\n- "I saw the back door was unlocked, so I walked in"\n- "I only took what I could carry"\n- "I didn\'t break anything to get inside"\n\n**Physical Evidence**:\n- Kitchen window broken from outside\n- Glass shards found on kitchen floor\n- Back door deadbolt engaged from inside\n- Muddy footprints leading from kitchen window\n\n**Stolen Items**:\n- Laptop, jewelry, and cash\n- Items were found in suspect\'s car'
);

SET @puzzle2_id = LAST_INSERT_ID();

INSERT INTO statements (puzzle_id, statement_order, statement_text, is_correct_answer, category) VALUES
(@puzzle2_id, 1, 'Family left for vacation on Monday at 8:00 AM', FALSE, 'timeline'),
(@puzzle2_id, 2, '"I saw the back door was unlocked, so I walked in"', TRUE, 'witness'),
(@puzzle2_id, 3, 'Kitchen window broken from outside', FALSE, 'physical_evidence'),
(@puzzle2_id, 4, 'Back door deadbolt engaged from inside', FALSE, 'physical_evidence'),
(@puzzle2_id, 5, 'Muddy footprints leading from kitchen window', FALSE, 'physical_evidence'),
(@puzzle2_id, 6, 'Items were found in suspect\'s car', FALSE, 'evidence');

INSERT INTO hints (puzzle_id, hint_order, hint_text) VALUES
(@puzzle2_id, 1, 'Compare the suspect\'s statement to the physical evidence found at the scene.'),
(@puzzle2_id, 2, 'If the door was unlocked, why is there broken glass and a specific entry point?');

INSERT INTO solutions (puzzle_id, explanation, detailed_reasoning) VALUES
(@puzzle2_id,
'The suspect claims the back door was unlocked, but the physical evidence contradicts this—the kitchen window was broken from outside, glass is on the floor inside, and there are muddy footprints from the window. If the door was unlocked, why break the window?',
'The suspect\'s statement that "I saw the back door was unlocked" cannot be true. The physical evidence clearly shows forced entry through the kitchen window (broken from outside, glass shards inside, muddy footprints leading from window). Additionally, the back door deadbolt was engaged from inside, meaning it was locked. The suspect broke in through the window, not through an unlocked door.');

INSERT INTO puzzle_stats (puzzle_id) VALUES (@puzzle2_id);

-- Day 3: The Parking Lot Collision
INSERT INTO puzzles (puzzle_date, title, difficulty, theme, case_summary, report_text) VALUES (
    DATE_ADD(CURDATE(), INTERVAL 2 DAY),
    'The Parking Lot Collision',
    'medium',
    'Traffic Incident',
    'Two cars collided in a parking lot. Both drivers claim the other was at fault. Security footage is corrupted, but witness statements and physical evidence tell the real story.',
    '**Incident**: Collision in Plaza Shopping Center parking lot\n\n**Driver A Statement** (Sarah Kim):\n- "I was backing out of my space slowly"\n- "I checked my mirrors and saw nothing"\n- "The other car came out of nowhere and hit my passenger side"\n- "My backup camera was working fine"\n\n**Driver B Statement** (Marcus Johnson):\n- "I was driving through the lot looking for a space"\n- "I was going the speed limit—5 mph"\n- "She backed right into me without looking"\n\n**Physical Evidence**:\n- Damage to Driver A\'s driver-side rear quarter panel\n- Damage to Driver B\'s front bumper (passenger side)\n- Driver A\'s backup camera disconnected (no power)\n- Skid marks from Driver B\'s vehicle indicating sudden braking\n\n**Witness** (lot attendant):\n- "I heard tires screeching, then a crash"\n- "The sedan was moving forward when they collided"'
);

SET @puzzle3_id = LAST_INSERT_ID();

INSERT INTO statements (puzzle_id, statement_order, statement_text, is_correct_answer, category) VALUES
(@puzzle3_id, 1, '"I checked my mirrors and saw nothing"', FALSE, 'witness'),
(@puzzle3_id, 2, '"My backup camera was working fine"', TRUE, 'witness'),
(@puzzle3_id, 3, '"I was going the speed limit—5 mph"', FALSE, 'witness'),
(@puzzle3_id, 4, 'Damage to Driver A\'s driver-side rear quarter panel', FALSE, 'physical_evidence'),
(@puzzle3_id, 5, 'Driver A\'s backup camera disconnected (no power)', FALSE, 'physical_evidence'),
(@puzzle3_id, 6, 'Skid marks from Driver B\'s vehicle indicating sudden braking', FALSE, 'physical_evidence');

INSERT INTO hints (puzzle_id, hint_order, hint_text) VALUES
(@puzzle3_id, 1, 'Look at what Driver A said about their equipment versus what was actually found.'),
(@puzzle3_id, 2, 'Can a backup camera work if it has no power?');

INSERT INTO solutions (puzzle_id, explanation, detailed_reasoning) VALUES
(@puzzle3_id,
'Driver A claims "My backup camera was working fine," but the physical evidence shows the backup camera was disconnected and had no power. She couldn\'t have known it was working if it wasn\'t connected.',
'Driver A stated her backup camera was working fine, which is impossible because investigators found it was disconnected with no power. This lie suggests she knew she wasn\'t properly checking her surroundings. The damage pattern (her driver-side rear quarter panel hit by the other car\'s front passenger side) also supports that she was backing out when Driver B was passing through the lane, and she failed to yield.');

INSERT INTO puzzle_stats (puzzle_id) VALUES (@puzzle3_id);

-- Day 4: The Missing Medication
INSERT INTO puzzles (puzzle_date, title, difficulty, theme, case_summary, report_text) VALUES (
    DATE_ADD(CURDATE(), INTERVAL 3 DAY),
    'The Missing Medication',
    'medium',
    'Pharmacy Theft',
    'Prescription medication went missing from a locked pharmacy cabinet. Only two employees had access, and both deny taking it. The security logs reveal who\'s lying.',
    '**Incident**: 100 pills of controlled medication missing\n\n**Access Log**:\n- Cabinet requires key card + 6-digit PIN\n- Only 2 employees authorized: Alex (day shift) and Jordan (night shift)\n- Last successful access: Tuesday 11:45 PM (Jordan\'s PIN)\n- Previous access: Tuesday 2:30 PM (Alex\'s PIN)\n\n**Alex\'s Statement** (day shift pharmacist):\n- "I accessed the cabinet at 2:30 PM to fill a prescription"\n- "I locked it immediately after"\n- "I left work at 6:00 PM"\n- "I was home all evening—my partner can confirm"\n\n**Jordan\'s Statement** (night shift pharmacist):\n- "I came in at 10:00 PM for my shift"\n- "I never opened the cabinet that night"\n- "I didn\'t need to access any controlled substances"\n- "The cabinet was locked when I checked it at midnight"\n\n**Physical Evidence**:\n- Cabinet shows no signs of forced entry\n- Security cameras offline from 10:00 PM - 1:00 AM (scheduled maintenance)\n- Empty medication bottle found in break room trash'
);

SET @puzzle4_id = LAST_INSERT_ID();

INSERT INTO statements (puzzle_id, statement_order, statement_text, is_correct_answer, category) VALUES
(@puzzle4_id, 1, 'Last successful access: Tuesday 11:45 PM (Jordan\'s PIN)', FALSE, 'security_log'),
(@puzzle4_id, 2, '"I accessed the cabinet at 2:30 PM to fill a prescription"', FALSE, 'witness'),
(@puzzle4_id, 3, '"I never opened the cabinet that night"', TRUE, 'witness'),
(@puzzle4_id, 4, '"The cabinet was locked when I checked it at midnight"', FALSE, 'witness'),
(@puzzle4_id, 5, 'Security cameras offline from 10:00 PM - 1:00 AM', FALSE, 'security_log'),
(@puzzle4_id, 6, 'Empty medication bottle found in break room trash', FALSE, 'physical_evidence');

INSERT INTO hints (puzzle_id, hint_order, hint_text) VALUES
(@puzzle4_id, 1, 'The access log doesn\'t lie. Compare what people said they did with what the system recorded.'),
(@puzzle4_id, 2, 'If Jordan never opened the cabinet, why does the log show their PIN was used?');

INSERT INTO solutions (puzzle_id, explanation, detailed_reasoning) VALUES
(@puzzle4_id,
'Jordan claims "I never opened the cabinet that night," but the security log shows the last access was at 11:45 PM using Jordan\'s PIN. The system doesn\'t make mistakes—Jordan is lying.',
'The access log is an objective record that shows Jordan\'s PIN was used to access the cabinet at 11:45 PM on Tuesday night. Jordan explicitly states "I never opened the cabinet that night," which directly contradicts this electronic evidence. While it\'s theoretically possible someone else used Jordan\'s PIN, Jordan would have reported this security breach. The statement is clearly false, and Jordan took the medication during the camera maintenance window.');

INSERT INTO puzzle_stats (puzzle_id) VALUES (@puzzle4_id);

-- Day 5: The Restaurant Alibi
INSERT INTO puzzles (puzzle_date, title, difficulty, theme, case_summary, report_text) VALUES (
    DATE_ADD(CURDATE(), INTERVAL 4 DAY),
    'The Restaurant Alibi',
    'hard',
    'Assault Investigation',
    'A suspect claims they were at a restaurant during an assault that occurred across town. The restaurant receipt and witness accounts seem to support the alibi, but one detail doesn\'t quite add up.',
    '**Incident**: Assault at 7:45 PM on Oak Street\n\n**Suspect Alibi** (Taylor Brooks):\n- "I was having dinner at Luigi\'s Restaurant from 7:00 PM to 8:30 PM"\n- "I have the receipt—it\'s timestamped 8:15 PM"\n- "The waiter can confirm I was there the whole time"\n- "It\'s a 25-minute drive from Luigi\'s to Oak Street"\n\n**Receipt Details**:\n- Luigi\'s Restaurant\n- Table 12\n- Server: Mike\n- Time: 8:15 PM\n- Items: Pasta, salad, wine\n- Paid with credit card\n\n**Waiter Statement** (Mike Rodriguez):\n- "Taylor came in around 7:00 PM"\n- "They seemed relaxed, no rush"\n- "I brought the check around 8:10 PM"\n- "They left shortly after paying"\n\n**Additional Evidence**:\n- Assault victim describes attacker matching Taylor\'s description\n- Security camera near assault shows someone matching description at 7:45 PM\n- Luigi\'s Restaurant is 25 minutes from Oak Street\n- Taylor\'s car found parked near Oak Street at 9:00 PM'
);

SET @puzzle5_id = LAST_INSERT_ID();

INSERT INTO statements (puzzle_id, statement_order, statement_text, is_correct_answer, category) VALUES
(@puzzle5_id, 1, '"I have the receipt—it\'s timestamped 8:15 PM"', FALSE, 'witness'),
(@puzzle5_id, 2, '"It\'s a 25-minute drive from Luigi\'s to Oak Street"', FALSE, 'witness'),
(@puzzle5_id, 3, '"They seemed relaxed, no rush"', FALSE, 'witness'),
(@puzzle5_id, 4, '"I was having dinner at Luigi\'s Restaurant from 7:00 PM to 8:30 PM"', TRUE, 'witness'),
(@puzzle5_id, 5, 'Security camera near assault shows someone matching description at 7:45 PM', FALSE, 'evidence'),
(@puzzle5_id, 6, 'Taylor\'s car found parked near Oak Street at 9:00 PM', FALSE, 'evidence');

INSERT INTO hints (puzzle_id, hint_order, hint_text) VALUES
(@puzzle5_id, 1, 'Think about what can be proven versus what can only be claimed. What\'s the actual evidence?'),
(@puzzle5_id, 2, 'A receipt proves when you paid, not when you arrived. Could someone arrive later than claimed?');

INSERT INTO solutions (puzzle_id, explanation, detailed_reasoning) VALUES
(@puzzle5_id,
'Taylor claims to have been at the restaurant "from 7:00 PM to 8:30 PM," but the receipt only proves payment at 8:15 PM. There\'s no proof of arrival time. Taylor could have committed the assault at 7:45 PM and arrived at the restaurant at 8:00 PM.',
'The critical flaw in Taylor\'s alibi is the claim of being at Luigi\'s from 7:00 PM to 8:30 PM. While the receipt is timestamped 8:15 PM and the waiter says Taylor "came in around 7:00 PM," receipts only prove payment time, not arrival time. The waiter\'s estimate of "around 7:00 PM" is not precise. Taylor could have: (1) Committed the assault at 7:45 PM on Oak Street, (2) Driven 25 minutes to Luigi\'s, arriving around 8:10 PM, (3) Quickly ordered and paid by 8:15 PM, (4) Created a false alibi. The car found near Oak Street at 9:00 PM further supports this timeline.');

INSERT INTO puzzle_stats (puzzle_id) VALUES (@puzzle5_id);

-- Day 6: The Office Fire
INSERT INTO puzzles (puzzle_date, title, difficulty, theme, case_summary, report_text) VALUES (
    DATE_ADD(CURDATE(), INTERVAL 5 DAY),
    'The Office Fire',
    'medium',
    'Arson',
    'A small fire started in an office building after hours. The fire marshal suspects arson, and building access logs point to one person who was inside. Their explanation has a critical flaw.',
    '**Incident**: Fire in 5th floor office, started around 8:30 PM\n\n**Building Access Log**:\n- Key card required for after-hours entry (after 6:00 PM)\n- Only one entry after 6:00 PM: Riley Chen at 7:15 PM\n- No exits recorded until fire alarm at 8:35 PM\n- Riley\'s exit: 8:36 PM\n\n**Riley\'s Statement**:\n- "I came back to get my laptop I forgot"\n- "I was only on the 3rd floor where my office is"\n- "I grabbed my laptop and left immediately"\n- "I was in the building maybe 5 minutes total"\n- "I heard the fire alarm as I was leaving"\n\n**Fire Marshal Report**:\n- Fire started in storage room on 5th floor\n- Accelerant (lighter fluid) detected\n- Fire started approximately 8:30 PM\n- Storage room has no security cameras\n\n**Physical Evidence**:\n- Riley\'s laptop was found in their car\n- Building has elevator and stairs\n- Storage room door requires key card access'
);

SET @puzzle6_id = LAST_INSERT_ID();

INSERT INTO statements (puzzle_id, statement_order, statement_text, is_correct_answer, category) VALUES
(@puzzle6_id, 1, 'Only one entry after 6:00 PM: Riley Chen at 7:15 PM', FALSE, 'security_log'),
(@puzzle6_id, 2, '"I was only on the 3rd floor where my office is"', FALSE, 'witness'),
(@puzzle6_id, 3, '"I was in the building maybe 5 minutes total"', TRUE, 'witness'),
(@puzzle6_id, 4, 'Fire started approximately 8:30 PM', FALSE, 'evidence'),
(@puzzle6_id, 5, 'Riley\'s exit: 8:36 PM', FALSE, 'security_log'),
(@puzzle6_id, 6, 'Storage room door requires key card access', FALSE, 'building_info');

INSERT INTO hints (puzzle_id, hint_order, hint_text) VALUES
(@puzzle6_id, 1, 'Check the timestamps carefully. How long was Riley actually in the building?'),
(@puzzle6_id, 2, 'If Riley entered at 7:15 PM and left at 8:36 PM, that\'s more than 5 minutes...');

INSERT INTO solutions (puzzle_id, explanation, detailed_reasoning) VALUES
(@puzzle6_id,
'Riley claims to have been "in the building maybe 5 minutes total," but the access log shows entry at 7:15 PM and exit at 8:36 PM—that\'s 81 minutes, not 5 minutes. This massive discrepancy reveals the lie.',
'Riley\'s statement that they were in the building "maybe 5 minutes total" is contradicted by the building\'s electronic access log. Entry was logged at 7:15 PM and exit at 8:36 PM, which is 1 hour and 21 minutes (81 minutes). This is not a small estimation error—it\'s a deliberate lie to avoid explaining what they were actually doing during that time. The fire started at 8:30 PM on the 5th floor, and Riley was the only person in the building with key card access to the storage room. The lie about the time reveals consciousness of guilt.');

INSERT INTO puzzle_stats (puzzle_id) VALUES (@puzzle6_id);

-- Day 7: The Stolen Package
INSERT INTO puzzles (puzzle_date, title, difficulty, theme, case_summary, report_text) VALUES (
    DATE_ADD(CURDATE(), INTERVAL 6 DAY),
    'The Stolen Package',
    'easy',
    'Package Theft',
    'A valuable package was stolen from an apartment building lobby. The delivery driver and a resident are both under suspicion. Ring doorbell footage and timestamps tell the real story.',
    '**Incident**: Package stolen from 123 Pine Street Apartments\n\n**Timeline**:\n- 2:15 PM: Delivery driver scans package as "delivered"\n- 2:17 PM: Doorbell camera shows driver placing package in lobby\n- 2:45 PM: Resident Emma walks through lobby (doorbell footage)\n- 3:30 PM: Homeowner comes home, package is gone\n\n**Delivery Driver Statement** (Pat Wilson):\n- "I delivered it at 2:15 PM according to my scanner"\n- "I placed it inside the lobby door"\n- "The package was there when I left"\n- "I took a photo of it in the lobby for proof"\n\n**Resident Statement** (Emma Torres, lives in building):\n- "I walked through the lobby around 2:45 PM"\n- "I didn\'t see any packages there"\n- "The lobby was empty except for the mail table"\n- "I went straight to the elevator"\n\n**Evidence**:\n- Delivery photo timestamp: 2:16 PM (shows package in lobby)\n- Doorbell camera confirms package placed at 2:17 PM\n- Doorbell camera shows Emma at 2:45 PM carrying a large shopping bag\n- No other residents entered between 2:17 PM and 3:30 PM'
);

SET @puzzle7_id = LAST_INSERT_ID();

INSERT INTO statements (puzzle_id, statement_order, statement_text, is_correct_answer, category) VALUES
(@puzzle7_id, 1, '2:15 PM: Delivery driver scans package as "delivered"', FALSE, 'timeline'),
(@puzzle7_id, 2, '"I placed it inside the lobby door"', FALSE, 'witness'),
(@puzzle7_id, 3, '"I didn\'t see any packages there"', TRUE, 'witness'),
(@puzzle7_id, 4, 'Delivery photo timestamp: 2:16 PM (shows package in lobby)', FALSE, 'evidence'),
(@puzzle7_id, 5, 'Doorbell camera shows Emma at 2:45 PM carrying a large shopping bag', FALSE, 'evidence'),
(@puzzle7_id, 6, 'No other residents entered between 2:17 PM and 3:30 PM', FALSE, 'timeline');

INSERT INTO hints (puzzle_id, hint_order, hint_text) VALUES
(@puzzle7_id, 1, 'The cameras and photos don\'t lie. What does the objective evidence show?'),
(@puzzle7_id, 2, 'If the package was photographed in the lobby at 2:16 PM and Emma walked through at 2:45 PM, could she really not see it?');

INSERT INTO solutions (puzzle_id, explanation, detailed_reasoning) VALUES
(@puzzle7_id,
'Emma claims "I didn\'t see any packages there" when she walked through at 2:45 PM, but the photo evidence and doorbell camera confirm the package was delivered at 2:17 PM. The package couldn\'t disappear by itself, and no one else entered. Emma is lying.',
'Emma\'s statement that she "didn\'t see any packages there" at 2:45 PM cannot be true. The delivery photo timestamped at 2:16 PM clearly shows the package in the lobby, and the doorbell camera confirms it was placed there at 2:17 PM. The doorbell camera also shows that no other residents entered the lobby between 2:17 PM and 3:30 PM except Emma at 2:45 PM. The package was definitely there when Emma walked through—she was carrying a large shopping bag that could easily conceal the stolen package. Emma took the package and lied about not seeing it.');

INSERT INTO puzzle_stats (puzzle_id) VALUES (@puzzle7_id);
