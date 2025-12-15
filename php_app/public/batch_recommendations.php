<?php
require_once '../lib/config.php';
require_once '../lib/auth.php';
require_once '../lib/database.php';
require_once '../lib/utils.php';

session_start();
Auth::requireAuth();

// Only lecturers can access batch recommendations
if ($_SESSION['role'] !== 'lecturer') {
    http_response_code(403);
    exit('Access denied');
}

$db = Database::getInstance();
$lecturer_id = $_SESSION['user_id'];

// Get student ID from request
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

// Verify student is in lecturer's groups
$student = $db->fetchOne(
    "SELECT u.* FROM users u
     JOIN group_students gs ON u.id = gs.student_id
     JOIN lecturer_groups lg ON gs.group_id = lg.id
     WHERE u.id = ? AND lg.lecturer_id = ?",
    [$student_id, $lecturer_id]
);

if (!$student) {
    http_response_code(404);
    exit('Student not found');
}

// Get latest prediction for the student
$prediction = $db->fetchOne(
    "SELECT * FROM predictions 
     WHERE user_id = ? 
     ORDER BY created_at DESC 
     LIMIT 1",
    [$student_id]
);

if (!$prediction) {
    echo '<div class="alert alert-warning">No prediction data available for this student.</div>';
    exit;
}

// Generate recommendations based on performance
$performance = $prediction['predicted_performance'];
$confidence = $prediction['confidence_level'];

// Performance-based recommendations
if ($performance >= 80) {
    $performance_category = 'Excellent';
    $performance_color = 'success';
    $main_recommendation = 'Maintain current study habits and focus on advanced topics.';
    $specific_tips = [
        'Explore advanced concepts in strongest subjects',
        'Consider peer tutoring opportunities',
        'Participate in academic competitions',
        'Develop research projects'
    ];
} elseif ($performance >= 70) {
    $performance_category = 'Good';
    $performance_color = 'primary';
    $main_recommendation = 'Focus on consistency and minor improvements in weaker areas.';
    $specific_tips = [
        'Practice time management for exams',
        'Review and strengthen foundational concepts',
        'Work on exam strategy and question analysis',
        'Set specific improvement goals'
    ];
} elseif ($performance >= 60) {
    $performance_category = 'Average';
    $performance_color = 'warning';
    $main_recommendation = 'Develop structured study routines and target specific weaknesses.';
    $specific_tips = [
        'Create a weekly study schedule',
        'Focus on understanding core concepts',
        'Practice with past examination papers',
        'Seek help in challenging subjects'
    ];
} elseif ($performance >= 50) {
    $performance_category = 'Needs Improvement';
    $performance_color = 'orange';
    $main_recommendation = 'Significant improvement needed. Develop foundational knowledge and study skills.';
    $specific_tips = [
        'Start with basic concepts and build up',
        'Get additional tutoring support',
        'Break down complex topics into smaller parts',
        'Practice regularly with guided support'
    ];
} else {
    $performance_category = 'Poor';
    $performance_color = 'danger';
    $main_recommendation = 'Immediate intervention required. Focus on foundational concepts and study habits.';
    $specific_tips = [
        'Seek immediate academic support',
        'Develop basic study skills first',
        'Work on one subject at a time',
        'Set achievable short-term goals'
    ];
}

// Confidence-based recommendations
if ($confidence < 60) {
    $confidence_recommendation = 'Low prediction confidence suggests need for more consistent academic data.';
    $confidence_tips = [
        'Ensure consistent study patterns',
        'Provide more complete academic information',
        'Focus on building foundational knowledge',
        'Regular practice and review'
    ];
} else {
    $confidence_recommendation = 'Good prediction confidence indicates reliable performance assessment.';
    $confidence_tips = [
        'Continue current assessment patterns',
        'Trust the performance indicators',
        'Use results for accurate planning'
    ];
}

// Get weak subjects analysis
$weak_subjects = [];
for ($i = 1; $i <= 9; $i++) {
    $subject_name = $prediction["subject{$i}_name"];
    $subject_grade = $prediction["subject{$i}_grade"];
    
    if (!empty($subject_name) && !empty($subject_grade) && $subject_grade >= 5) {
        $weak_subjects[] = [
            'name' => $subject_name,
            'grade' => $subject_grade,
            'recommendation' => getSubjectRecommendation($subject_name, $subject_grade)
        ];
    }
}
?>

<div class="batch-recommendations">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Study Recommendations for <?php echo htmlspecialchars($student['username']); ?></h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>

    <!-- Performance Overview -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-<?php echo $performance_color; ?>">
                <div class="card-header bg-<?php echo $performance_color; ?> text-white">
                    <h6 class="card-title mb-0">Performance Assessment</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <h4 class="text-<?php echo $performance_color; ?>"><?php echo $performance; ?>%</h4>
                            <small class="text-muted">Predicted Performance</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-<?php echo $confidence >= 70 ? 'success' : ($confidence >= 60 ? 'warning' : 'danger'); ?>">
                                <?php echo $confidence; ?>%
                            </h4>
                            <small class="text-muted">Confidence Level</small>
                        </div>
                    </div>
                    <div class="mt-3">
                        <strong>Category:</strong> 
                        <span class="badge bg-<?php echo $performance_color; ?>"><?php echo $performance_category; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-light">
                <div class="card-header">
                    <h6 class="card-title mb-0">Overall Recommendation</h6>
                </div>
                <div class="card-body">
                    <p class="mb-2"><?php echo $main_recommendation; ?></p>
                    <small class="text-muted">Based on performance analysis and prediction confidence.</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Specific Recommendations -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">Specific Study Recommendations</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($specific_tips as $tip): ?>
                            <li class="list-group-item">
                                <i class="bi bi-check-circle text-success me-2"></i>
                                <?php echo htmlspecialchars($tip); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Weak Subjects Focus -->
    <?php if (!empty($weak_subjects)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-exclamation-triangle"></i> Focus Areas
                    </h6>
                </div>
                <div class="card-body">
                    <?php foreach ($weak_subjects as $subject): ?>
                        <div class="mb-3 p-3 border rounded bg-warning bg-opacity-10">
                            <h6 class="text-warning mb-2">
                                <i class="bi bi-book"></i>
                                <?php echo htmlspecialchars($subject['name']); ?>
                                (Grade: <?php echo $subject['grade']; ?>)
                            </h6>
                            <p class="mb-1 small"><?php echo htmlspecialchars($subject['recommendation']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Confidence Recommendations -->
    <div class="row">
        <div class="col-12">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h6 class="card-title mb-0">Confidence Assessment</h6>
                </div>
                <div class="card-body">
                    <p class="mb-3"><?php echo $confidence_recommendation; ?></p>
                    <ul class="list-unstyled">
                        <?php foreach ($confidence_tips as $tip): ?>
                            <li class="mb-2">
                                <i class="bi bi-lightbulb text-info me-2"></i>
                                <?php echo htmlspecialchars($tip); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Plan -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card bg-primary bg-opacity-10">
                <div class="card-body">
                    <h6 class="card-title">Suggested Action Plan</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <strong>Short-term (2 weeks):</strong>
                            <ul class="small">
                                <li>Implement 2-3 key recommendations</li>
                                <li>Schedule study sessions</li>
                                <li>Set specific goals</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <strong>Medium-term (1 month):</strong>
                            <ul class="small">
                                <li>Review progress weekly</li>
                                <li>Adjust strategies as needed</li>
                                <li>Expand to additional subjects</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <strong>Long-term (3 months):</strong>
                            <ul class="small">
                                <li>Achieve target performance level</li>
                                <li>Develop independent study skills</li>
                                <li>Prepare for examinations</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Helper function for subject-specific recommendations
function getSubjectRecommendation($subject, $grade) {
    $recommendations = [
        'Mathematics' => [
            'Practice different types of problems daily',
            'Focus on understanding formulas and concepts',
            'Work on past examination questions',
            'Join study groups for complex topics'
        ],
        'English Language' => [
            'Read extensively to improve vocabulary',
            'Practice essay writing regularly',
            'Work on comprehension skills',
            'Focus on grammar rules and usage'
        ],
        'Physics' => [
            'Understand fundamental concepts first',
            'Practice numerical problems regularly',
            'Conduct simple experiments when possible',
            'Relate theories to real-world applications'
        ],
        'Chemistry' => [
            'Memorize periodic table elements',
            'Practice chemical equations balancing',
            'Understand organic chemistry basics',
            'Focus on laboratory techniques and safety'
        ],
        'Biology' => [
            'Create diagrams for biological processes',
            'Memorize classification systems',
            'Understand human anatomy thoroughly',
            'Study ecological relationships'
        ]
    ];
    
    $subject_tips = $recommendations[$subject] ?? [
        'Review fundamental concepts',
        'Practice past questions regularly',
        'Create comprehensive study notes',
        'Seek additional help from teachers'
    ];
    
    return $subject_tips[0] . ' Focus on building foundational knowledge and regular practice.';
}
?>