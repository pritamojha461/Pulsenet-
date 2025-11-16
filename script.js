// ==================== Mood Detection ====================
const moodButtons = document.querySelectorAll('.mood-btn');
const moodResult = document.getElementById('mood-result');

moodButtons.forEach(button => {
    button.addEventListener('click', function() {
        // Remove active class from all buttons
        moodButtons.forEach(btn => btn.classList.remove('active'));
        
        // Add active class to clicked button
        this.classList.add('active');
        
        // Get mood data
        const mood = this.getAttribute('data-mood');
        const moodText = this.textContent;
        
        // Apply mood-based theme
        applyMoodTheme(mood);
        
        // Show result
        displayMoodResult(mood, moodText);
        
        // Store mood in localStorage
        saveMoodToHistory(mood);
        
        // Get AI recommendations
        getMoodRecommendations(mood);
    });
});

// Apply mood-based color theme
function applyMoodTheme(mood) {
    const body = document.body;
    
    // Remove previous mood classes
    body.classList.remove('mood-happy', 'mood-sad', 'mood-anxious', 'mood-calm', 'mood-stressed');
    
    // Add new mood class
    body.classList.add(`mood-${mood}`);
}

// Display mood detection result
function displayMoodResult(mood, moodText) {
    const responses = {
        happy: '🌟 Great! Keep spreading that positive energy!',
        sad: '💙 It\'s okay to feel down. Try a mindfulness exercise to uplift your mood.',
        anxious: '🌬️ Take a deep breath. A breathing exercise can help calm your nerves.',
        calm: '🧘 You\'re in a peaceful state. Perfect for meditation!',
        stressed: '🎯 Let\'s reduce that stress. Try our breathing or meditation exercises.'
    };
    
    moodResult.textContent = responses[mood];
    moodResult.style.display = 'block';
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        moodResult.style.display = 'none';
    }, 5000);
}

// Get AI-powered mood recommendations
function getMoodRecommendations(mood) {
    const recommendations = {
        happy: {
            exercise: 'meditation',
            circle: 'mindfulness',
            message: 'Share your happiness with others in the Mindfulness Community!'
        },
        sad: {
            exercise: 'meditation',
            circle: 'anxiety',
            message: 'Connect with others who understand. Join the Anxiety Support Group.'
        },
        anxious: {
            exercise: 'breathing',
            circle: 'anxiety',
            message: 'Try breathing exercises first, then join support circle.'
        },
        calm: {
            exercise: 'meditation',
            circle: 'mindfulness',
            message: 'Deepen your calm with guided meditation.'
        },
        stressed: {
            exercise: 'breathing',
            circle: 'sleep',
            message: 'Release stress with breathing. Wind down with our community.'
        }
    };
    
    console.log('Recommended for you:', recommendations[mood]);
}

// Save mood to history
function saveMoodToHistory(mood) {
    let moodHistory = JSON.parse(localStorage.getItem('moodHistory')) || [];
    
    moodHistory.push({
        mood: mood,
        timestamp: new Date().toISOString()
    });
    
    // Keep only last 30 entries
    if (moodHistory.length > 30) {
        moodHistory.shift();
    }
    
    localStorage.setItem('moodHistory', JSON.stringify(moodHistory));
}

// ==================== Mindfulness Exercises ====================
let exerciseTimer = null;

function startExercise(type) {
    const exercises = {
        breathing: {
            title: '🌬️ Breathing Exercise',
            duration: 300, // 5 minutes
            steps: [
                'Breathe in for 4 seconds...',
                'Hold for 4 seconds...',
                'Breathe out for 4 seconds...',
                'Hold for 4 seconds...'
            ]
        },
        meditation: {
            title: '🧘 Guided Meditation',
            duration: 600, // 10 minutes
            steps: [
                'Find a comfortable position...',
                'Close your eyes gently...',
                'Focus on your breath...',
                'Let thoughts pass like clouds...'
            ]
        },
        sleep: {
            title: '😴 Sleep Aid',
            duration: 900, // 15 minutes
            steps: [
                'Dim the lights...',
                'Relax your body...',
                'Listen to gentle sounds...',
                'Let sleep come naturally...'
            ]
        }
    };
    
    const exercise = exercises[type];
    
    if (!exercise) return;
    
    // Create modal
    const modal = createExerciseModal(exercise);
    document.body.appendChild(modal);
    
    // Start timer
    runExerciseTimer(exercise.duration, modal);
}

function createExerciseModal(exercise) {
    const modal = document.createElement('div');
    modal.className = 'exercise-modal';
    modal.innerHTML = `
        <div class="exercise-content">
            <button class="close-btn" onclick="this.parentElement.parentElement.remove()">✕</button>
            <h2>${exercise.title}</h2>
            <div class="exercise-display">
                <p id="exercise-text">${exercise.steps[0]}</p>
                <div class="timer">
                    <span id="time-remaining">${formatTime(exercise.duration)}</span>
                </div>
            </div>
            <div class="exercise-controls">
                <button onclick="pauseExercise()">Pause</button>
                <button onclick="this.parentElement.parentElement.parentElement.remove()">Exit</button>
            </div>
        </div>
    `;
    
    // Add styles
    const style = document.createElement('style');
    style.innerHTML = `
        .exercise-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .exercise-content {
            background: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            max-width: 500px;
            position: relative;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        .exercise-display {
            margin: 30px 0;
        }
        
        #exercise-text {
            font-size: 24px;
            color: #6366f1;
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        .timer {
            font-size: 48px;
            font-weight: bold;
            color: #ec4899;
            font-family: 'Courier New', monospace;
        }
        
        .exercise-controls {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .exercise-controls button {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            background: #6366f1;
            color: white;
            transition: all 0.3s ease;
        }
        
        .exercise-controls button:hover {
            background: #4f46e5;
            transform: scale(1.05);
        }
    `;
    document.head.appendChild(style);
    
    return modal;
}

function runExerciseTimer(duration, modal) {
    let remaining = duration;
    
    exerciseTimer = setInterval(() => {
        remaining--;
        
        const timeDisplay = modal.querySelector('#time-remaining');
        if (timeDisplay) {
            timeDisplay.textContent = formatTime(remaining);
        }
        
        if (remaining <= 0) {
            clearInterval(exerciseTimer);
            alert('Great job! Exercise completed. 🎉');
            modal.remove();
        }
    }, 1000);
}

function pauseExercise() {
    if (exerciseTimer) {
        clearInterval(exerciseTimer);
        alert('Exercise paused. Take your time!');
    }
}

function formatTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
}

// ==================== Support Circles ====================
const activeCircles = {};

function joinCircle(circleId) {
    if (!activeCircles[circleId]) {
        activeCircles[circleId] = {
            id: circleId,
            members: [],
            messages: [],
            joinedAt: new Date()
        };
    }
    
    // Create chat interface
    createChatInterface(circleId);
}

function createChatInterface(circleId) {
    const circleNames = {
        anxiety: 'Anxiety Support Group',
        mindfulness: 'Mindfulness Community',
        sleep: 'Sleep & Wellness'
    };
    
    const chat = document.createElement('div');
    chat.className = 'chat-modal';
    chat.innerHTML = `
        <div class="chat-container">
            <div class="chat-header">
                <h3>${circleNames[circleId]}</h3>
                <button class="close-btn" onclick="this.parentElement.parentElement.remove()">✕</button>
            </div>
            <div class="chat-messages" id="chat-${circleId}">
                <p class="system-message">Welcome to the ${circleNames[circleId]}! 💬</p>
                <p class="system-message">This is a safe space for peer support. Remember: be respectful and supportive.</p>
            </div>
            <div class="chat-input-area">
                <input type="text" placeholder="Share your thoughts..." id="input-${circleId}" />
                <button onclick="sendMessage('${circleId}')">Send</button>
            </div>
        </div>
    `;
    
    // Add styles
    const style = document.createElement('style');
    style.innerHTML = `
        .chat-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: flex-end;
            align-items: flex-end;
            z-index: 1000;
        }
        
        .chat-container {
            background: white;
            width: 100%;
            max-width: 400px;
            height: 600px;
            border-radius: 20px 20px 0 0;
            display: flex;
            flex-direction: column;
            box-shadow: 0 -5px 40px rgba(0, 0, 0, 0.2);
        }
        
        .chat-header {
            background: linear-gradient(135deg, #6366f1, #ec4899);
            color: white;
            padding: 20px;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .chat-header h3 {
            margin: 0;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }
        
        .system-message {
            background: #f3f4f6;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 13px;
            color: #666;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .user-message {
            background: #6366f1;
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            word-wrap: break-word;
        }
        
        .chat-input-area {
            display: flex;
            gap: 10px;
            padding: 15px;
            border-top: 1px solid #e5e7eb;
        }
        
        .chat-input-area input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .chat-input-area button {
            padding: 10px 20px;
            background: #6366f1;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .chat-input-area button:hover {
            background: #4f46e5;
        }
    `;
    document.head.appendChild(style);
    
    document.body.appendChild(chat);
    
    // Focus on input
    const input = document.getElementById(`input-${circleId}`);
    if (input) input.focus();
}

function sendMessage(circleId) {
    const input = document.getElementById(`input-${circleId}`);
    const messagesDiv = document.getElementById(`chat-${circleId}`);
    
    if (!input || !input.value.trim()) return;
    
    const message = input.value.trim();
    
    // Validate message (peer safety)
    if (isMessageSafe(message)) {
        // Create message element
        const msgEl = document.createElement('p');
        msgEl.className = 'user-message';
        msgEl.textContent = message;
        messagesDiv.appendChild(msgEl);
        
        // Clear input
        input.value = '';
        
        // Auto-scroll to bottom
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
        
        // Simulate peer response
        setTimeout(() => {
            const response = getPeerResponse(circleId);
            const responseEl = document.createElement('p');
            responseEl.className = 'user-message';
            responseEl.style.background = '#ec4899';
            responseEl.textContent = response;
            messagesDiv.appendChild(responseEl);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }, 1000);
    } else {
        alert('⚠️ Please maintain respectful communication.');
        input.value = '';
    }
}

// Peer safety: Filter inappropriate content
function isMessageSafe(message) {
    const blockedWords = ['hate', 'kill', 'suicide', 'harm'];
    const lowerMsg = message.toLowerCase();
    
    for (let word of blockedWords) {
        if (lowerMsg.includes(word)) {
            return false;
        }
    }
    
    return message.length > 0 && message.length < 500;
}

// Generate supportive peer responses
function getPeerResponse(circleId) {
    const responses = {
        anxiety: [
            '❤️ I understand. You\'re not alone in this.',
            '💪 You\'re doing great by reaching out!',
            '🌟 Remember, anxiety is temporary. You\'ll get through this.',
            '🫂 Sending you strength and compassion.'
        ],
        mindfulness: [
            '🧘 That\'s a beautiful perspective!',
            '✨ Thank you for sharing your journey.',
            '🌱 Growth happens one moment at a time.',
            '💫 Your mindfulness journey inspires me!'
        ],
        sleep: [
            '😴 Sleep is healing. Take care of yourself.',
            '🌙 Rest well, you deserve it!',
            '💤 Wishing you peaceful dreams.',
            '🌟 Better sleep, better days ahead!'
        ]
    };
    
    const circleResponses = responses[circleId] || responses.anxiety;
    return circleResponses[Math.floor(Math.random() * circleResponses.length)];
}

// ==================== Dark Mode Toggle ====================
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
}

// Load dark mode preference
window.addEventListener('DOMContentLoaded', () => {
    const darkMode = localStorage.getItem('darkMode') === 'true';
    if (darkMode) {
        document.body.classList.add('dark-mode');
    }
});

// ==================== Accessibility & Keyboard Navigation ====================
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        // Close any open modals
        document.querySelectorAll('.exercise-modal, .chat-modal').forEach(modal => modal.remove());
    }
});

console.log('🎯 PulseNet initialized! Mental wellness at your fingertips.');