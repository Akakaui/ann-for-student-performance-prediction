import sys
import json
import numpy as np
from sklearn.ensemble import RandomForestRegressor
import joblib
import os
import warnings
warnings.filterwarnings('ignore')

class PerformancePredictor:
    def __init__(self):
        self.model = None
        self.feature_names = [
            'current_cgpa', 'average_grade', 'parent_education', 'study_hours', 
            'learning_materials', 'school_type', 'family_income'
        ]
        self.load_model()
    
    def load_model(self):
        """Load pre-trained model or create a new one"""
        model_path = os.path.join(os.path.dirname(__file__), '../models/performance_model.pkl')
        
        if os.path.exists(model_path):
            try:
                self.model = joblib.load(model_path)
                print("Model loaded successfully", file=sys.stderr)
            except Exception as e:
                print(f"Error loading model: {e}", file=sys.stderr)
                self.create_new_model()
        else:
            self.create_new_model()
            os.makedirs(os.path.dirname(model_path), exist_ok=True)
            joblib.dump(self.model, model_path)
            print("New model created and saved", file=sys.stderr)
    
    def create_new_model(self):
        """Create and train a new model with realistic data"""
        # Use simpler model for faster predictions
        self.model = RandomForestRegressor(
            n_estimators=50,  # Reduced for speed
            max_depth=8,
            random_state=42,
            n_jobs=-1  # Use all CPU cores
        )
        self.train_realistic_model()
    
    def train_realistic_model(self):
        """Train model with realistic educational data patterns"""
        np.random.seed(42)
        n_samples = 2000  # Reduced for faster training
        
        # Realistic feature distributions
        current_cgpa = np.random.uniform(1.5, 5.0, n_samples)  # CGPA range
        average_grade = np.random.uniform(1, 9, n_samples)     # WAEC/NECO grades
        parent_education = np.random.choice([0, 1, 2, 3], n_samples, p=[0.1, 0.3, 0.4, 0.2])
        study_hours = np.clip(np.random.normal(20, 8, n_samples), 5, 40)
        learning_materials = np.random.choice([0, 1, 2, 3], n_samples, p=[0.2, 0.4, 0.3, 0.1])
        school_type = np.random.choice([0, 1], n_samples, p=[0.7, 0.3])
        family_income = np.random.choice([0, 1, 2], n_samples, p=[0.4, 0.5, 0.1])
        
        X = np.column_stack([
            current_cgpa, average_grade, parent_education, study_hours, 
            learning_materials, school_type, family_income
        ])
        
        # Generate realistic performance scores
        y = (
            40 +  # base performance
            (X[:, 0] * 8) +  # CGPA impact (very important)
            (9 - X[:, 1]) * 2.5 +  # WAEC grade impact (lower is better)
            X[:, 2] * 3.5 +  # parent education
            X[:, 3] * 0.5 +  # study hours
            X[:, 4] * 3.0 +  # learning materials
            X[:, 5] * 2.0 +  # school type
            X[:, 6] * 2.5 +  # family income
            np.random.normal(0, 8, n_samples)  # random noise
        )
        
        # Clip to realistic percentage range
        y = np.clip(y, 0, 100)
        
        # Train the model
        self.model.fit(X, y)
        print("Model training completed", file=sys.stderr)
    
    def preprocess_features(self, input_data):
        """Preprocess input features for prediction"""
        subjects = input_data.get('subjects', [])
        
        # Calculate average WAEC/NECO grade
        grades = [subject['grade'] for subject in subjects if subject.get('grade')]
        if not grades:
            average_grade = 4.5  # Default average
        else:
            average_grade = sum(grades) / len(grades)
        
        # Get current CGPA
        current_cgpa = float(input_data.get('current_cgpa', 3.0))
        
        # Convert socio-economic factors to numeric
        parent_education_map = {'none': 0, 'primary': 1, 'secondary': 2, 'tertiary': 3}
        learning_materials_map = {'poor': 0, 'average': 1, 'good': 2, 'excellent': 3}
        school_type_map = {'public': 0, 'private': 1}
        family_income_map = {'low': 0, 'middle': 1, 'high': 2}
        
        features = [
            current_cgpa,
            average_grade,
            parent_education_map.get(input_data.get('parent_education_level', 'secondary'), 1),
            input_data.get('study_hours_per_week', 15),
            learning_materials_map.get(input_data.get('access_to_learning_materials', 'average'), 1),
            school_type_map.get(input_data.get('school_type', 'public'), 0),
            family_income_map.get(input_data.get('family_income_level', 'middle'), 1)
        ]
        
        return np.array([features])
    
    def predict(self, input_data):
        """Make performance prediction - optimized for speed"""
        try:
            X = self.preprocess_features(input_data)
            
            # Make prediction
            prediction = self.model.predict(X)[0]
            prediction = max(0, min(100, round(prediction, 1)))
            
            # Calculate feature importance
            feature_importance = self.calculate_feature_importance(X[0])
            
            # Determine confidence
            confidence = self.calculate_confidence(input_data, X[0])
            
            interpretation = self.get_interpretation(prediction)
            
            return {
                'success': True,
                'predicted_performance': prediction,
                'confidence_level': confidence,
                'feature_contributions': feature_importance,
                'interpretation': interpretation,
                'model_type': 'optimized_random_forest'
            }
            
        except Exception as e:
            print(f"Prediction error: {e}", file=sys.stderr)
            # Fast fallback prediction
            return self.fallback_prediction(input_data)
    
    def calculate_feature_importance(self, features):
        """Calculate contribution of each feature"""
        if hasattr(self.model, 'feature_importances_'):
            importance_scores = self.model.feature_importances_
        else:
            # Default importance scores based on educational research
            importance_scores = [0.30, 0.20, 0.12, 0.15, 0.10, 0.06, 0.07]
        
        contributions = {}
        for i, feature_name in enumerate(self.feature_names):
            contributions[feature_name] = {
                'importance': round(importance_scores[i] * 100, 1),
                'value': round(features[i], 2),
                'impact': self.get_impact_direction(feature_name, features[i])
            }
        
        return contributions
    
    def get_impact_direction(self, feature_name, value):
        """Determine if feature has positive or negative impact"""
        if feature_name == 'current_cgpa':
            return 'positive' if value >= 3.0 else 'negative'
        elif feature_name == 'average_grade':
            return 'positive' if value <= 4.5 else 'negative'  # Lower WAEC grades are better
        elif feature_name in ['parent_education', 'study_hours', 'learning_materials', 'family_income']:
            return 'positive' if value >= 1.5 else 'negative'
        elif feature_name == 'school_type':
            return 'positive' if value == 1 else 'neutral'
        return 'neutral'
    
    def calculate_confidence(self, input_data, features):
        """Calculate confidence level based on input quality"""
        confidence = 75.0
        
        # Adjust based on number of subjects
        subjects = input_data.get('subjects', [])
        valid_subjects = [s for s in subjects if s.get('name') and s.get('grade')]
        
        if len(valid_subjects) >= 7:
            confidence += 10
        elif len(valid_subjects) <= 4:
            confidence -= 15
        
        # Adjust based on CGPA (more realistic = higher confidence)
        cgpa = features[0]
        if 2.0 <= cgpa <= 4.5:
            confidence += 5
        elif cgpa < 1.0 or cgpa > 5.0:
            confidence -= 10
        
        return max(50, min(95, round(confidence, 1)))
    
    def get_interpretation(self, performance):
        """Provide interpretation of prediction result"""
        if performance >= 80:
            return "Excellent potential! Your current academic profile indicates strong readiness for WAEC/NECO exams."
        elif performance >= 65:
            return "Good performance potential! You're well positioned for success with your current preparation."
        elif performance >= 50:
            return "Average potential. Solid foundation with opportunities for improvement in key areas."
        else:
            return "Needs improvement. Focus on core subjects and consider additional academic support."
    
    def fallback_prediction(self, input_data):
        """Fast fallback prediction if main model fails"""
        try:
            # Simple weighted average calculation
            subjects = input_data.get('subjects', [])
            grades = [s['grade'] for s in subjects if s.get('grade')]
            
            if grades:
                avg_grade = sum(grades) / len(grades)
                # Convert WAEC grade to percentage (lower grades = higher percentage)
                grade_score = (9 - avg_grade) * 10
            else:
                grade_score = 50
            
            cgpa = float(input_data.get('current_cgpa', 3.0))
            cgpa_score = cgpa * 15  # CGPA contributes significantly
            
            # Base score with simple adjustments
            prediction = 40 + grade_score + cgpa_score
            prediction = max(0, min(100, round(prediction, 1)))
            
            return {
                'success': True,
                'predicted_performance': prediction,
                'confidence_level': 60.0,
                'feature_contributions': {},
                'interpretation': 'Prediction completed using fast calculation.',
                'model_type': 'fallback_calculation'
            }
        except Exception as e:
            return {
                'success': False,
                'error': f'Prediction failed: {str(e)}',
                'predicted_performance': 0,
                'confidence_level': 0,
                'feature_contributions': {},
                'interpretation': 'Prediction system temporarily unavailable.'
            }

def main():
    if len(sys.argv) < 2:
        result = {
            'success': False,
            'error': 'No input data provided',
            'predicted_performance': 0,
            'confidence_level': 0,
            'feature_contributions': {}
        }
        print(json.dumps(result))
        return
    
    try:
        input_json = sys.argv[1]
        input_data = json.loads(input_json)
        
        predictor = PerformancePredictor()
        result = predictor.predict(input_data)
        
        print(json.dumps(result))
        
    except Exception as e:
        error_result = {
            'success': False,
            'error': f'Prediction failed: {str(e)}',
            'predicted_performance': 0,
            'confidence_level': 0,
            'feature_contributions': {}
        }
        print(json.dumps(error_result))

if __name__ == '__main__':
    main()