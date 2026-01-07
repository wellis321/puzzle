<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/Session.php';
require_once 'includes/Puzzle.php';
require_once 'includes/Game.php';

$session = new Session();
$puzzle = new Puzzle();
$game = new Game($session->getSessionId());

// Get user rank and progress
$rankProgress = $game->getRankProgress();
$ranksTableMissing = isset($rankProgress['table_missing']) && $rankProgress['table_missing'];

// Get selected difficulty from URL parameter (default to 'medium' if not specified)
$selectedDifficulty = isset($_GET['difficulty']) && in_array($_GET['difficulty'], ['easy', 'medium', 'hard']) 
    ? $_GET['difficulty'] 
    : 'medium';

// Initialize variables
$todaysPuzzles = [];
$selectedPuzzleId = null;

// DEV MODE: Allow puzzle selection via URL parameter
if (EnvLoader::get('APP_ENV') === 'development' && isset($_GET['puzzle_id'])) {
    $selectedPuzzleId = (int)$_GET['puzzle_id'];
    $todaysPuzzle = $puzzle->getPuzzleById($selectedPuzzleId);
    $selectedDifficulty = $todaysPuzzle['difficulty']; // Use puzzle's actual difficulty
} else {
    // Get all puzzles for today
    $todaysPuzzles = $puzzle->getTodaysPuzzles();
    
    // If no puzzles available for today, show error
    if (empty($todaysPuzzles)) {
        $todaysPuzzle = null;
    } else {
        // Find the selected difficulty puzzle
        $todaysPuzzle = null;
        foreach ($todaysPuzzles as $p) {
            if ($p['difficulty'] === $selectedDifficulty) {
                $todaysPuzzle = $p;
                break;
            }
        }
        
        // If selected difficulty not available, default to first available
        if (!$todaysPuzzle && !empty($todaysPuzzles)) {
            $todaysPuzzle = $todaysPuzzles[0];
            $selectedDifficulty = $todaysPuzzle['difficulty'];
        }
    }
}

if (!$todaysPuzzle) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo APP_NAME; ?> - No Puzzle Available</title>
        <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    </head>
    <body>
        <div class="container">
            <header class="header">
                <h1><?php echo APP_NAME; ?></h1>
                <p class="tagline">Solve the daily mystery</p>
            </header>
            <main class="game-container">
                <div style="text-align: center; padding: 60px 20px;">
                    <h2 style="color: #8b4513; font-size: 32px; margin-bottom: 20px; font-family: 'Courier New', 'Courier', monospace;">No Case Available</h2>
                    <p style="font-size: 20px; color: #5a5a5a; line-height: 1.8;">No puzzle is available for today. Please check back later!</p>
                </div>
            </main>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$puzzleId = $todaysPuzzle['id'];
$statements = $puzzle->getStatements($puzzleId);
$hints = $puzzle->getHints($puzzleId);

// Check if user has already completed this puzzle
$completion = $game->getCompletion($puzzleId);
$attempts = $game->getAttempts($puzzleId);
$attemptCount = count($attempts);

// DEV MODE: Get all puzzles for selector
$allPuzzles = [];
if (EnvLoader::get('APP_ENV') === 'development') {
    $allPuzzles = $puzzle->getAllPuzzles();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Daily Mystery Puzzle</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-top">
                <div class="header-left">
                    <h1><?php echo APP_NAME; ?></h1>
                    <p class="tagline">Solve the daily mystery</p>
                </div>
                <?php if (!$ranksTableMissing): ?>
                <div class="detective-rank rank-level-<?php echo $rankProgress['current_level']; ?>">
                    <div class="rank-badge">
                        <div class="rank-icon">🔍</div>
                        <div class="rank-info">
                            <div class="rank-name"><?php echo htmlspecialchars($rankProgress['current_rank']); ?></div>
                            <?php if (!isset($rankProgress['max_rank']) || !$rankProgress['max_rank']): ?>
                                <div class="rank-progress">
                                    <div class="rank-progress-bar">
                                        <div class="rank-progress-fill" style="width: <?php echo $rankProgress['percentage']; ?>%"></div>
                                    </div>
                                    <div class="rank-progress-text">
                                        <?php echo $rankProgress['progress']; ?> / <?php echo $rankProgress['needed']; ?> → <?php echo htmlspecialchars($rankProgress['next_rank']); ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="rank-max">⭐ MAX RANK ACHIEVED ⭐</div>
                            <?php endif; ?>
                            <div class="rank-stats">
                                <span class="stat-item">🏆 <?php echo $rankProgress['stats']['total_completions']; ?> cases</span>
                                <span class="stat-item">🔥 <?php echo $rankProgress['stats']['current_streak']; ?> day streak</span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="rank-setup-notice">
                    <div style="background: #fff3cd; border: 2px solid #ffc107; padding: 10px 15px; border-radius: 4px; font-size: 12px; color: #856404; max-width: 300px;">
                        <strong>⚠️ Rank System Setup Needed:</strong><br>
                        Run <code>database/add-ranks-table.sql</code> to enable detective ranks
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </header>

        <?php if (EnvLoader::get('APP_ENV') === 'development' && !empty($allPuzzles)): ?>
        <!-- DEV MODE: Puzzle Selector -->
        <div class="dev-selector">
            <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <label for="puzzle_select" style="color: #d4af37; font-weight: 600;">DEV MODE - Select Puzzle:</label>
                <select name="puzzle_id" id="puzzle_select" onchange="this.form.submit()" style="padding: 8px 12px; background: rgba(0,0,0,0.5); color: #e8e8e8; border: 1px solid #d4af37; border-radius: 4px; font-size: 14px;">
                    <?php foreach ($allPuzzles as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo ($p['id'] == $puzzleId) ? 'selected' : ''; ?>>
                            <?php echo date('M j, Y', strtotime($p['puzzle_date'])); ?> - <?php echo ucfirst($p['difficulty']); ?> - <?php echo htmlspecialchars($p['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <a href="?" style="padding: 8px 16px; background: rgba(212,175,55,0.2); color: #d4af37; text-decoration: none; border-radius: 4px; border: 1px solid #d4af37; font-size: 13px;">Reset to Today</a>
                <a href="admin/" style="padding: 8px 16px; background: rgba(212,175,55,0.2); color: #d4af37; text-decoration: none; border-radius: 4px; border: 1px solid #d4af37; font-size: 13px;">Admin Panel</a>
            </form>
        </div>
        <?php endif; ?>

        <?php 
        // Show difficulty selector if multiple puzzles available for today
        if (!isset($selectedPuzzleId) && isset($todaysPuzzles) && count($todaysPuzzles) > 1): 
            // Check which puzzles are completed
            $completedPuzzles = [];
            foreach ($todaysPuzzles as $p) {
                $completionCheck = $game->getCompletion($p['id']);
                $completedPuzzles[$p['difficulty']] = $completionCheck ? true : false;
            }
        ?>
        <!-- Difficulty Selector -->
        <div class="difficulty-selector">
            <div class="difficulty-selector-label">Choose Difficulty:</div>
            <div class="difficulty-tabs">
                <?php 
                $difficulties = ['easy', 'medium', 'hard'];
                foreach ($difficulties as $diff): 
                    $puzzleExists = false;
                    foreach ($todaysPuzzles as $p) {
                        if ($p['difficulty'] === $diff) {
                            $puzzleExists = true;
                            $puzzleForDiff = $p;
                            break;
                        }
                    }
                    
                    if (!$puzzleExists) continue;
                    
                    $isActive = ($selectedDifficulty === $diff);
                    $isCompleted = isset($completedPuzzles[$diff]) && $completedPuzzles[$diff];
                    $diffLabel = ucfirst($diff);
                ?>
                    <a href="?difficulty=<?php echo $diff; ?>" 
                       class="difficulty-tab difficulty-tab-<?php echo $diff; ?> <?php echo $isActive ? 'active' : ''; ?> <?php echo $isCompleted ? 'completed' : ''; ?>">
                        <span class="difficulty-tab-label"><?php echo $diffLabel; ?></span>
                        <?php if ($isCompleted): ?>
                            <span class="difficulty-tab-checkmark">✓</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <main class="game-container" data-case-number="<?php echo $puzzleId; ?>">
            <?php if ($completion): ?>
                <!-- Completed State -->
                <div class="completion-screen">
                    <h2><?php echo $completion['solved'] ? 'Case Solved' : 'Case Closed'; ?></h2>

                    <?php
                    $scoreText = [
                        'perfect' => 'Perfect Deduction',
                        'close' => 'Close Call',
                        'lucky' => 'Lucky Guess'
                    ];
                    ?>

                    <div class="score">
                        <span class="score-text"><?php echo $scoreText[$completion['score']]; ?></span>
                    </div>

                    <div class="attempts-display">
                        <?php
                        foreach ($attempts as $attempt) {
                            if ($attempt['is_correct']) {
                                echo '<span class="attempt-icon attempt-correct" title="Correct">✓</span>';
                            } else {
                                echo '<span class="attempt-icon attempt-wrong" title="Incorrect">✗</span>';
                            }
                        }
                        ?>
                    </div>

                    <?php
                    $solution = $puzzle->getSolution($puzzleId);
                    if ($solution):
                    ?>
                        <div class="solution">
                            <h3>The Solution</h3>
                            
                            <?php if (!empty($solution['image_path'])): ?>
                                <div class="solution-image-container">
                                    <img src="<?php echo htmlspecialchars($solution['image_path']); ?>" 
                                         alt="Solution illustration" 
                                         class="solution-image"
                                         loading="lazy">
                                </div>
                            <?php endif; ?>
                            
                            <p><strong>Why it doesn't fit:</strong></p>
                            <p><?php echo nl2br(htmlspecialchars($solution['explanation'])); ?></p>

                            <?php if (!empty($solution['detailed_reasoning'])): ?>
                            <details style="margin-top: 20px;">
                                <summary style="cursor: pointer; font-weight: bold;">Show detailed reasoning</summary>
                                <div style="margin-top: 10px;">
                                    <p><?php echo nl2br(htmlspecialchars($solution['detailed_reasoning'])); ?></p>
                                </div>
                            </details>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="share-section">
                        <h3>Share Your Result</h3>
                        <div class="share-box">
                            <textarea id="share-text" readonly><?php echo $game->getShareableResult($puzzleId, 1); ?></textarea>
                            <button onclick="copyShare()" class="btn">Copy to Clipboard</button>
                        </div>
                    </div>

                    <?php if (EnvLoader::get('APP_ENV') === 'development'): ?>
                        <div style="margin-top: 20px;">
                            <a href="?puzzle_id=<?php echo $puzzleId; ?>&reset=1" onclick="return confirm('Reset your progress on this puzzle?')" class="btn" style="background: rgba(244,67,54,0.3); color: #e57373;">Reset This Puzzle</a>
                        </div>
                    <?php else: ?>
                        <div class="next-puzzle">
                            <p>Next case in:</p>
                            <div id="countdown" class="countdown"></div>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- Active Game -->
                <div class="puzzle-info">
                    <div class="info-row">
                        <span class="difficulty-badge difficulty-<?php echo $todaysPuzzle['difficulty']; ?>">
                            <?php echo ucfirst($todaysPuzzle['difficulty']); ?>
                        </span>
                        <span class="theme"><?php echo htmlspecialchars($todaysPuzzle['theme']); ?></span>
                    </div>
                    <h2><?php echo htmlspecialchars($todaysPuzzle['title']); ?></h2>
                </div>

                <!-- Progressive Information Disclosure -->
                <div id="info-sections">
                    <!-- Case Summary Section -->
                    <div class="info-section active" data-section="0">
                        <div class="case-summary">
                            <p><?php echo nl2br(htmlspecialchars($todaysPuzzle['case_summary'])); ?></p>
                        </div>
                        <div class="section-navigation">
                            <button class="btn btn-back" style="visibility: hidden;">← Back</button>
                            <button class="btn continue-btn" onclick="showNextSection()">Continue to Report →</button>
                        </div>
                    </div>

                    <?php
                    // Parse report text into sections
                    $reportText = $todaysPuzzle['report_text'];
                    // Split by double newlines first, then check for ** markers
                    $rawSections = preg_split('/\n\n+/', $reportText);
                    $sections = [];
                    
                    foreach ($rawSections as $rawSection):
                        $trimmed = trim($rawSection);
                        if ($trimmed === '') continue;
                        
                        // Check if section starts with ** (title)
                        if (preg_match('/^\*\*(.*?)\*\*\s*:?\s*/', $trimmed, $titleMatch)) {
                            $title = trim($titleMatch[1]);
                            // Remove the title and any trailing colon/whitespace
                            $content = preg_replace('/^\*\*(.*?)\*\*\s*:?\s*/', '', $trimmed);
                            $content = trim($content);
                            // Remove leading colon if it exists
                            $content = preg_replace('/^:\s*/', '', $content);
                        } else {
                            $title = '';
                            $content = $trimmed;
                            // Remove leading colon if it exists
                            $content = preg_replace('/^:\s*/', '', $content);
                        }
                        
                        // Clean up any remaining leading punctuation issues
                        $content = trim($content);
                        
                        $sections[] = ['title' => $title, 'content' => $content];
                    endforeach;
                    
                    $sectionIndex = 1;
                    $totalReportSections = count($sections);
                    
                    foreach ($sections as $section):
                        // Format the content
                        $formattedContent = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $section['content']);
                        $formattedContent = nl2br($formattedContent);
                    ?>
                        <div class="info-section" data-section="<?php echo $sectionIndex; ?>">
                            <div class="report-section">
                                <?php if ($section['title']): ?>
                                    <div class="report-section-title"><?php echo htmlspecialchars($section['title']); ?></div>
                                <?php endif; ?>
                                <div class="report-section-content"><?php echo $formattedContent; ?></div>
                            </div>
                            <div class="section-navigation">
                                <button class="btn btn-back" onclick="showPreviousSection()" <?php echo ($sectionIndex === 1) ? 'style="visibility: hidden;"' : ''; ?>>
                                    ← Back
                                </button>
                                <button class="btn continue-btn" onclick="showNextSection()">
                                    <?php echo ($sectionIndex < $totalReportSections) ? 'Continue →' : 'View Questions →'; ?>
                                </button>
                            </div>
                        </div>
                    <?php
                        $sectionIndex++;
                    endforeach;
                    ?>
                </div>

                <!-- Questions Section (hidden initially) -->
                <div class="questions-section" id="questions-section">
                    <div class="questions-header">
                        <button class="btn btn-back" onclick="showPreviousSection()">
                            ← Back to Report
                        </button>
                        <button class="btn btn-secondary view-all-btn" onclick="toggleModal()">
                            <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm7 16H5V5h2v3h10V5h2v14z"/>
                            </svg>
                            See All Information Again
                        </button>
                    </div>
                    
                    <div class="task">
                        <h3>Find the ONE detail that doesn't fit</h3>
                        <p class="attempts-remaining">Attempts remaining: <strong><?php echo 3 - $attemptCount; ?></strong></p>
                    </div>

                    <div class="statements" id="statements">
                        <?php foreach ($statements as $stmt): ?>
                            <button class="statement-btn" data-statement-id="<?php echo $stmt['id']; ?>">
                                <span class="statement-number"><?php echo $stmt['statement_order']; ?></span>
                                <span class="statement-text"><?php echo htmlspecialchars($stmt['statement_text']); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($attemptCount > 0): ?>
                        <div class="hints-section">
                            <h3>Hints</h3>
                            <?php
                            $availableHints = min($attemptCount, count($hints));
                            for ($i = 0; $i < $availableHints; $i++):
                            ?>
                                <div class="hint">
                                    <?php echo htmlspecialchars($hints[$i]['hint_text']); ?>
                                </div>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>

                    <div id="feedback" class="feedback"></div>
                </div>

                <!-- Modal for viewing all information -->
                <div class="modal-overlay" id="info-modal" onclick="if(event.target === this) toggleModal()">
                    <div class="modal" onclick="event.stopPropagation()">
                        <div class="modal-header">
                            <h2>Case File - All Information</h2>
                            <button class="modal-close" onclick="toggleModal()">×</button>
                        </div>
                        <div class="modal-content">
                            <div class="modal-section">
                                <h3>Case Summary</h3>
                                <p><?php echo nl2br(htmlspecialchars($todaysPuzzle['case_summary'])); ?></p>
                            </div>
                            <?php
                            // Show all report sections in modal (reuse same parsing logic)
                            $reportText = $todaysPuzzle['report_text'];
                            $rawSections = preg_split('/\n\n+/', $reportText);
                            
                            foreach ($rawSections as $rawSection):
                                $trimmed = trim($rawSection);
                                if ($trimmed === '') continue;
                                
                                // Check if section starts with ** (title)
                                if (preg_match('/^\*\*(.*?)\*\*\s*:?\s*/', $trimmed, $titleMatch)) {
                                    $title = trim($titleMatch[1]);
                                    // Remove the title and any trailing colon/whitespace
                                    $content = preg_replace('/^\*\*(.*?)\*\*\s*:?\s*/', '', $trimmed);
                                    $content = trim($content);
                                    // Remove leading colon if it exists
                                    $content = preg_replace('/^:\s*/', '', $content);
                                } else {
                                    $title = '';
                                    $content = $trimmed;
                                    // Remove leading colon if it exists
                                    $content = preg_replace('/^:\s*/', '', $content);
                                }
                                
                                // Clean up any remaining leading punctuation issues
                                $content = trim($content);
                                
                                $formattedContent = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);
                                $formattedContent = nl2br($formattedContent);
                            ?>
                                <div class="modal-section">
                                    <?php if ($title): ?>
                                        <h3><?php echo htmlspecialchars($title); ?></h3>
                                    <?php endif; ?>
                                    <?php echo $formattedContent; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>

        <footer class="footer">
            <p>A new mystery every day at midnight</p>
        </footer>
    </div>

    <script>
        const puzzleId = <?php echo $puzzleId; ?>;
        const isCompleted = <?php echo $completion ? 'true' : 'false'; ?>;
        let currentSection = 0;
        const totalSections = document.querySelectorAll('.info-section').length;

        // Progressive disclosure functions
        function showNextSection() {
            const currentSectionEl = document.querySelector(`.info-section[data-section="${currentSection}"]`);
            if (currentSectionEl) {
                currentSectionEl.classList.remove('active');
            }
            
            currentSection++;
            
            if (currentSection < totalSections) {
                const nextSectionEl = document.querySelector(`.info-section[data-section="${currentSection}"]`);
                if (nextSectionEl) {
                    nextSectionEl.classList.add('active');
                    // Update back button visibility
                    updateNavigationButtons();
                    // Scroll to top of section
                    nextSectionEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            } else {
                // All sections shown, show questions
                document.getElementById('questions-section').classList.add('active');
                document.getElementById('questions-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function showPreviousSection() {
            // Hide questions section if we're going back from it
            const questionsSection = document.getElementById('questions-section');
            if (questionsSection && questionsSection.classList.contains('active')) {
                questionsSection.classList.remove('active');
                // Set currentSection to the last info section
                currentSection = totalSections - 1;
            }
            
            const currentSectionEl = document.querySelector(`.info-section[data-section="${currentSection}"]`);
            if (currentSectionEl) {
                currentSectionEl.classList.remove('active');
            }
            
            if (currentSection > 0) {
                currentSection--;
                const prevSectionEl = document.querySelector(`.info-section[data-section="${currentSection}"]`);
                if (prevSectionEl) {
                    prevSectionEl.classList.add('active');
                    // Update back button visibility
                    updateNavigationButtons();
                    // Scroll to top of section
                    prevSectionEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        }

        function updateNavigationButtons() {
            // Update back button visibility for all sections
            document.querySelectorAll('.info-section').forEach((section, index) => {
                const backBtn = section.querySelector('.btn-back');
                if (backBtn) {
                    if (index === 0) {
                        backBtn.style.visibility = 'hidden';
                    } else {
                        backBtn.style.visibility = 'visible';
                    }
                }
            });
        }

        // Modal functions
        function toggleModal() {
            const modal = document.getElementById('info-modal');
            modal.classList.toggle('active');
            if (modal.classList.contains('active')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('info-modal');
                if (modal && modal.classList.contains('active')) {
                    toggleModal();
                }
            }
        });

        // Handle statement clicks
        document.querySelectorAll('.statement-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                if (this.disabled) return;

                const statementId = this.dataset.statementId;

                // Disable all buttons
                document.querySelectorAll('.statement-btn').forEach(b => b.disabled = true);

                try {
                    const response = await fetch('api/submit-answer.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            puzzle_id: puzzleId,
                            statement_id: statementId
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        if (result.is_correct) {
                            this.classList.add('correct');
                            showFeedback('✓ Correct! You found the inconsistency!', 'success');
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else {
                            this.classList.add('wrong');
                            if (result.attempts_remaining > 0) {
                                showFeedback(`✗ Not quite. ${result.attempts_remaining} attempt(s) remaining.`, 'error');
                                // Re-enable buttons
                                setTimeout(() => {
                                    document.querySelectorAll('.statement-btn:not(.wrong)').forEach(b => b.disabled = false);
                                }, 1500);
                            } else {
                                showFeedback('✗ Out of attempts. See the solution below.', 'error');
                                setTimeout(() => {
                                    window.location.reload();
                                }, 2000);
                            }
                        }
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showFeedback('An error occurred. Please try again.', 'error');
                    document.querySelectorAll('.statement-btn').forEach(b => b.disabled = false);
                }
            });
        });

        function showFeedback(message, type) {
            const feedback = document.getElementById('feedback');
            feedback.textContent = message;
            feedback.className = 'feedback feedback-' + type;
            feedback.style.display = 'block';
        }

        function copyShare() {
            const shareText = document.getElementById('share-text');
            shareText.select();
            document.execCommand('copy');
            alert('Copied to clipboard!');
        }

        // Countdown to next puzzle (only in production)
        if (isCompleted && '<?php echo EnvLoader::get('APP_ENV'); ?>' !== 'development') {
            function updateCountdown() {
                const now = new Date();
                const tomorrow = new Date(now);
                tomorrow.setDate(tomorrow.getDate() + 1);
                tomorrow.setHours(0, 0, 0, 0);

                const diff = tomorrow - now;
                const hours = Math.floor(diff / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((diff % (1000 * 60)) / 1000);

                document.getElementById('countdown').textContent =
                    `${hours}h ${minutes}m ${seconds}s`;
            }

            updateCountdown();
            setInterval(updateCountdown, 1000);
        }

        // DEV MODE: Handle reset
        <?php if (EnvLoader::get('APP_ENV') === 'development' && isset($_GET['reset'])): ?>
            // Clear this puzzle's session data
            fetch('api/dev-reset.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ puzzle_id: <?php echo $puzzleId; ?> })
            }).then(() => {
                window.location.href = '?puzzle_id=<?php echo $puzzleId; ?>';
            });
        <?php endif; ?>
    </script>
</body>
</html>
