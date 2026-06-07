<?php
class Recommendations {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Generate personalized recommendations based on prediction data
     */
    public function generateRecommendations($prediction_id, $user_id) {
        // Get prediction details
        $prediction = $this->db->fetchOne(
            "SELECT * FROM predictions WHERE id = ? AND user_id = ?",
            [$prediction_id, $user_id]
        );
        
        if (!$prediction) {
            return ['error' => 'Prediction not found'];
        }
        
        $recommendations = [
            'general' => $this->getGeneralRecommendations($prediction),
            'subject_specific' => $this->getSubjectRecommendations($prediction),
            'study_plan' => $this->generateStudyPlan($prediction),
            'timeline' => $this->getImprovementTimeline($prediction)
        ];
        
        // Save recommendations to database
        $this->saveRecommendations($prediction_id, $recommendations);
        
        return $recommendations;
    }
    
    /**
     * Get general performance recommendations
     */
    private function getGeneralRecommendations($prediction) {
        $performance = $prediction['predicted_performance'];
        $confidence = $prediction['confidence_level'];
        $exam_type = $prediction['exam_type'];
        
        $recommendations = [];
        
        // Performance-based recommendations
        if ($performance >= 80) {
            $recommendations[] = [
                'type' => 'excellent',
                'title' => 'Outstanding Performance!',
                'message' => 'Your predicted performance is excellent. Maintain your current study habits.',
                'priority' => 'low',
                'icon' => 'trophy'
            ];
        } elseif ($performance >= 70) {
            $recommendations[] = [
                'type' => 'good',
                'title' => 'Strong Performance',
                'message' => 'You\'re performing well. Focus on consistency and minor improvements.',
                'priority' => 'medium',
                'icon' => 'check-circle'
            ];
        } elseif ($performance >= 60) {
            $recommendations[] = [
                'type' => 'average',
                'title' => 'Solid Foundation',
                'message' => 'You have a good foundation. Target specific areas for improvement.',
                'priority' => 'high',
                'icon' => 'lightbulb'
            ];
        } else {
            $recommendations[] = [
                'type' => 'needs_improvement',
                'title' => 'Focus Required',
                'message' => 'Significant improvement needed. Develop structured study routines.',
                'priority' => 'critical',
                'icon' => 'exclamation-triangle'
            ];
        }
        
        // Confidence-based recommendations
        if ($confidence < 60) {
            $recommendations[] = [
                'type' => 'low_confidence',
                'title' => 'Build Confidence',
                'message' => 'Your prediction confidence is low. Focus on mastering core concepts.',
                'priority' => 'high',
                'icon' => 'shield'
            ];
        }
        
        // Exam type specific advice
        if ($exam_type === 'WAEC') {
            $recommendations[] = [
                'type' => 'exam_specific',
                'title' => 'WAEC Strategy',
                'message' => 'Focus on past questions and time management for WAEC exams.',
                'priority' => 'medium',
                'icon' => 'journal'
            ];
        } else {
            $recommendations[] = [
                'type' => 'exam_specific',
                'title' => 'NECO Approach',
                'message' => 'NECO requires strong conceptual understanding across all subjects.',
                'priority' => 'medium',
                'icon' => 'book'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Get subject-specific recommendations
     */
    private function getSubjectRecommendations($prediction) {
        $subject_recommendations = [];
        $weak_subjects = [];
        $strong_subjects = [];
        
        // Analyze subject performance
        for ($i = 1; $i <= 9; $i++) {
            $subject_name = $prediction["subject{$i}_name"];
            $subject_score = $prediction["subject{$i}_score"];
            
            if (!empty($subject_name) && !empty($subject_score)) {
                if ($subject_score < 60) {
                    $weak_subjects[] = [
                        'name' => $subject_name,
                        'score' => $subject_score,
                        'recommendations' => $this->getSubjectSpecificTips($subject_name, $subject_score)
                    ];
                } elseif ($subject_score >= 80) {
                    $strong_subjects[] = [
                        'name' => $subject_name,
                        'score' => $subject_score,
                        'message' => 'Excellent performance. Maintain your strength in this subject.'
                    ];
                }
            }
        }
        
        return [
            'weak_subjects' => $weak_subjects,
            'strong_subjects' => $strong_subjects
        ];
    }
    
    /**
     * Get specific tips for each subject
     */
    private function getSubjectSpecificTips($subject, $score) {
        $tips = [];
        
        $subject_tips = [
            'Mathematics' => [
                'Practice daily problem-solving',
                'Focus on understanding formulas',
                'Work on past examination questions',
                'Join study groups for complex topics'
            ],
            'English Language' => [
                'Read extensively to improve vocabulary',
                'Practice essay writing regularly',
                'Work on comprehension skills',
                'Focus on grammar rules'
            ],
            'Physics' => [
                'Understand fundamental concepts',
                'Practice numerical problems',
                'Conduct simple experiments',
                'Relate theories to real-world applications'
            ],
            'Chemistry' => [
                'Memorize periodic table elements',
                'Practice chemical equations',
                'Understand organic chemistry basics',
                'Focus on laboratory techniques'
            ],
            'Biology' => [
                'Create diagrams for biological processes',
                'Memorize classification systems',
                'Understand human anatomy',
                'Study ecological relationships'
            ],
            'Economics' => [
                'Understand basic economic principles',
                'Practice graph interpretations',
                'Study current economic events',
                'Focus on micro and macro economics'
            ],
            'Geography' => [
                'Study maps and map reading',
                'Understand weather patterns',
                'Learn about different ecosystems',
                'Focus on human geography'
            ],
            'Government' => [
                'Understand political systems',
                'Study constitutional frameworks',
                'Learn about governance structures',
                'Focus on current affairs'
            ]
        ];
        
        // Get general tips if subject not in list
        $available_tips = $subject_tips[$subject] ?? [
            'Review fundamental concepts',
            'Practice past questions',
            'Create study notes',
            'Seek help from teachers'
        ];
        
        // Select 2-3 most relevant tips based on score
        $selected_tips = array_slice($available_tips, 0, min(3, count($available_tips)));
        
        foreach ($selected_tips as $tip) {
            $tips[] = [
                'tip' => $tip,
                'estimated_improvement' => '5-10% with consistent practice'
            ];
        }
        
        return $tips;
    }
    
    /**
     * Generate personalized study plan
     */
    private function generateStudyPlan($prediction) {
        $performance = $prediction['predicted_performance'];
        $study_plan = [];
        
        if ($performance >= 80) {
            $study_plan = [
                [
                    'week' => 1,
                    'focus' => 'Advanced Topics',
                    'activities' => ['Challenge problems', 'Research projects', 'Peer teaching'],
                    'hours_weekly' => '10-12'
                ],
                [
                    'week' => 2,
                    'focus' => 'Exam Strategy',
                    'activities' => ['Past papers', 'Time management', 'Revision techniques'],
                    'hours_weekly' => '8-10'
                ]
            ];
        } elseif ($performance >= 70) {
            $study_plan = [
                [
                    'week' => 1,
                    'focus' => 'Weak Areas',
                    'activities' => ['Targeted practice', 'Concept review', 'Video tutorials'],
                    'hours_weekly' => '12-15'
                ],
                [
                    'week' => 2,
                    'focus' => 'Consolidation',
                    'activities' => ['Mixed practice', 'Mock tests', 'Error analysis'],
                    'hours_weekly' => '10-12'
                ]
            ];
        } else {
            $study_plan = [
                [
                    'week' => 1,
                    'focus' => 'Foundation Building',
                    'activities' => ['Basic concepts', 'Step-by-step learning', 'Guided practice'],
                    'hours_weekly' => '15-20'
                ],
                [
                    'week' => 2,
                    'focus' => 'Practice & Application',
                    'activities' => ['Practice problems', 'Concept application', 'Progress tests'],
                    'hours_weekly' => '12-15'
                ],
                [
                    'week' => 3,
                    'focus' => 'Review & Improvement',
                    'activities' => ['Error correction', 'Weak area focus', 'Mock exams'],
                    'hours_weekly' => '10-12'
                ]
            ];
        }
        
        return $study_plan;
    }
    
    /**
     * Get improvement timeline estimates
     */
    private function getImprovementTimeline($prediction) {
        $performance = $prediction['predicted_performance'];
        
        if ($performance >= 80) {
            return [
                'current_level' => 'Advanced',
                'next_milestone' => 'Maintain Excellence',
                'estimated_time' => 'Ongoing',
                'target_improvement' => 'Maintain above 80%'
            ];
        } elseif ($performance >= 70) {
            return [
                'current_level' => 'Proficient',
                'next_milestone' => 'Advanced Level',
                'estimated_time' => '4-6 weeks',
                'target_improvement' => 'Reach 80%+'
            ];
        } elseif ($performance >= 60) {
            return [
                'current_level' => 'Developing',
                'next_milestone' => 'Proficient Level',
                'estimated_time' => '6-8 weeks',
                'target_improvement' => 'Reach 70%+'
            ];
        } else {
            return [
                'current_level' => 'Foundation',
                'next_milestone' => 'Developing Level',
                'estimated_time' => '8-12 weeks',
                'target_improvement' => 'Reach 60%+'
            ];
        }
    }
    
    /**
     * Save recommendations to database
     */
    private function saveRecommendations($prediction_id, $recommendations) {
        $existing = $this->db->fetchOne(
            "SELECT id FROM recommendations WHERE prediction_id = ?",
            [$prediction_id]
        );

        if ($existing) {
            $this->db->query(
                "UPDATE recommendations SET recommendations = ?, updated_at = NOW() WHERE prediction_id = ?",
                [json_encode($recommendations), $prediction_id]
            );
        } else {
            $this->db->query(
                "INSERT INTO recommendations (prediction_id, recommendations, created_at, updated_at) 
                 VALUES (?, ?, NOW(), NOW())",
                [$prediction_id, json_encode($recommendations)]
            );
        }
    }
    
    /**
     * Get saved recommendations for a prediction
     */
    public function getSavedRecommendations($prediction_id) {
        $saved = $this->db->fetchOne(
            "SELECT recommendations FROM recommendations WHERE prediction_id = ?",
            [$prediction_id]
        );
        
        if ($saved && !empty($saved['recommendations'])) {
            return json_decode($saved['recommendations'], true);
        }
        
        return null;
    }
    
    /**
     * Get study tips by category
     */
    public function getStudyTips($category = 'general') {
        $tips_library = [
            'time_management' => [
                'Create a study schedule and stick to it',
                'Use the Pomodoro technique (25min study, 5min break)',
                'Prioritize difficult subjects during peak concentration hours',
                'Set specific goals for each study session'
            ],
            'exam_preparation' => [
                'Practice with past examination papers',
                'Simulate exam conditions during practice',
                'Focus on understanding marking schemes',
                'Develop effective time management strategies for exams'
            ],
            'subject_specific' => [
                'Mathematics: Practice different types of problems daily',
                'Sciences: Create concept maps and diagrams',
                'Languages: Regular reading and writing practice',
                'Social Sciences: Relate concepts to current events'
            ],
            'general' => [
                'Maintain consistent study routines',
                'Get adequate sleep before study sessions',
                'Stay hydrated and maintain proper nutrition',
                'Take regular breaks to avoid burnout',
                'Review material regularly instead of cramming'
            ]
        ];
        
        return $tips_library[$category] ?? $tips_library['general'];
    }
}
?>