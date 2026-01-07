-- Seed data: Sample Day 1 puzzle
USE mystery_puzzle;

-- Insert Day 1 puzzle
INSERT INTO puzzles (puzzle_date, title, difficulty, theme, case_summary, report_text) VALUES (
    CURDATE(),
    'The Office Laptop',
    'easy',
    'Office Theft',
    'A laptop was stolen from an office building overnight. Security footage and witness statements have been collected. One detail in the report doesn\'t add up.',
    '**Incident**: Laptop stolen from 3rd floor office\n\n**Security Log**:\n- Building locked at 6:00 PM\n- Motion sensor triggered on 3rd floor at 11:45 PM\n- Front door access card used at 11:52 PM (exit)\n\n**Suspect Statement** (Jamie Chen, cleaning staff):\n- "I finished cleaning the 3rd floor around 11:30 PM"\n- "I took the stairs down and left through the front door"\n- "I didn\'t see anyone else in the building"\n\n**Physical Evidence**:\n- Office window found closed and locked\n- Laptop charging cable left behind on desk\n- No signs of forced entry\n\n**Building Details**:\n- Elevator out of service for maintenance\n- Stairwell lights on motion sensors (auto-off after 2 minutes)\n- Security cameras offline due to software update'
);

-- Get the puzzle ID
SET @puzzle_id = LAST_INSERT_ID();

-- Insert statements for Day 1
INSERT INTO statements (puzzle_id, statement_order, statement_text, is_correct_answer, category) VALUES
(@puzzle_id, 1, 'Motion sensor triggered on 3rd floor at 11:45 PM', FALSE, 'security_log'),
(@puzzle_id, 2, '"I finished cleaning the 3rd floor around 11:30 PM"', TRUE, 'witness'),
(@puzzle_id, 3, 'Front door access card used at 11:52 PM (exit)', FALSE, 'security_log'),
(@puzzle_id, 4, 'Office window found closed and locked', FALSE, 'physical_evidence'),
(@puzzle_id, 5, 'Laptop charging cable left behind on desk', FALSE, 'physical_evidence'),
(@puzzle_id, 6, 'Elevator out of service for maintenance', FALSE, 'building_info');

-- Insert hints for Day 1
INSERT INTO hints (puzzle_id, hint_order, hint_text) VALUES
(@puzzle_id, 1, 'Pay close attention to the times. Do they all line up?'),
(@puzzle_id, 2, 'How long would it take to walk down stairs? Compare that to the security logs.');

-- Insert solution for Day 1
INSERT INTO solutions (puzzle_id, explanation, detailed_reasoning) VALUES
(@puzzle_id,
'Jamie claims to have finished cleaning at 11:30 PM, but the motion sensor triggered at 11:45 PM (15 minutes later). Since Jamie exited at 11:52 PM (only 7 minutes after the sensor), the timeline doesn\'t work—they couldn\'t have left the 3rd floor at 11:30 PM.',
'If Jamie finished cleaning at 11:30 PM and the motion sensor triggered at 11:45 PM, someone else must have been on the 3rd floor—or Jamie is lying. Since Jamie used their access card to exit at 11:52 PM (only 7 minutes after the motion sensor), and they claim to have left the 3rd floor at 11:30 PM, they would have needed 22 minutes to walk down the stairs. This is impossible for a simple stairwell descent. The truth: Jamie was still on the 3rd floor at 11:45 PM, triggering the motion sensor themselves.');

-- Initialize stats for Day 1
INSERT INTO puzzle_stats (puzzle_id, total_attempts, total_completions, total_solved, avg_attempts) VALUES
(@puzzle_id, 0, 0, 0, 0.00);
