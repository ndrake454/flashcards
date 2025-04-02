/**
 * Text-to-Speech Features for Flashcard App
 * Implements functionality to read feedback aloud
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Text-to-speech features initializing...');
    // Initialize TTS features
    initTextToSpeech();
    
    // Add event listeners for feedback container
    attachFeedbackListener();
});

/**
 * Attach listener for feedback display to add TTS button
 */
function attachFeedbackListener() {
    console.log('Attaching feedback listener...');
    // Use MutationObserver to detect when feedback is displayed
    const feedbackContainer = document.getElementById('feedbackContainer');
    
    if (feedbackContainer) {
        // Create a mutation observer to watch for changes to the feedback container
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    // Feedback content has been added
                    console.log('Feedback displayed, adding TTS button');
                    addTTSButton();
                }
            });
        });
        
        // Start observing the feedback container
        observer.observe(feedbackContainer, { childList: true });
        
        // Also listen for the custom event as a backup method
        document.addEventListener('feedbackDisplayed', function(event) {
            console.log('Feedback displayed event received');
            addTTSButton();
        });
    }
}

/**
 * Add text-to-speech button to feedback
 */
function addTTSButton() {
    const feedbackContainer = document.getElementById('feedbackContainer');
    const ttsButtonTemplate = document.getElementById('ttsButtonTemplate');
    
    // Check if TTS button already exists
    if (feedbackContainer && ttsButtonTemplate && !feedbackContainer.querySelector('#readFeedbackBtn')) {
        console.log('Adding TTS button to feedback');
        
        // Create button element directly instead of using template
        const ttsButtonDiv = document.createElement('div');
        ttsButtonDiv.className = 'text-center mt-3';
        
        const ttsButton = document.createElement('button');
        ttsButton.id = 'readFeedbackBtn';
        ttsButton.className = 'btn btn-outline-secondary btn-sm';
        ttsButton.innerHTML = '<i class="bi bi-volume-up me-1"></i> Read Feedback Aloud';
        
        ttsButtonDiv.appendChild(ttsButton);
        feedbackContainer.appendChild(ttsButtonDiv);
        
        // Add event listener to the button
        ttsButton.addEventListener('click', function() {
            readFeedbackAloud();
        });
        
        console.log('TTS button added and event listener attached');
    } else {
        console.log('TTS button already exists or required elements missing');
    }
}

/**
 * Initialize text-to-speech
 */
function initTextToSpeech() {
    console.log('Initializing text-to-speech...');
    
    // Check if browser supports speech synthesis
    if (!('speechSynthesis' in window)) {
        console.error('Text-to-speech not supported by this browser');
        return;
    }
    
    console.log('Text-to-speech supported by browser');
    
    // Make stopSpeech globally available
    window.stopSpeech = stopSpeech;
}

/**
 * Read feedback aloud using text-to-speech
 */
function readFeedbackAloud() {
    console.log('Reading feedback aloud...');
    
    // Check if browser supports speech synthesis
    if (!('speechSynthesis' in window)) {
        alert('Text-to-speech is not supported in your browser.');
        return;
    }
    
    // Get feedback content
    const feedbackContainer = document.getElementById('feedbackContainer');
    const feedbackCard = feedbackContainer.querySelector('.card');
    
    if (!feedbackCard) {
        console.error('Feedback card not found');
        return;
    }
    
    // Only get the feedback text (skip the header)
    const feedbackText = feedbackCard.querySelector('.card-text').textContent;
    
    console.log('Feedback text:', feedbackText);
    
    // Set text to read (only feedback text, not the header)
    let textToRead = feedbackText;
    
    // Get missing points if any
    const missingPoints = feedbackCard.querySelector('.missing-points');
    if (missingPoints) {
        textToRead += ' Key points to remember: ';
        const points = missingPoints.querySelectorAll('li');
        points.forEach((point, index) => {
            textToRead += 'Point ' + (index + 1) + ': ' + point.textContent + '. ';
        });
    }
    
    console.log('Text to read:', textToRead);
    
    // Create utterance
    const utterance = new SpeechSynthesisUtterance(textToRead);
    
    // Set properties
    utterance.lang = 'en-US';
    utterance.rate = 1.0;
    utterance.pitch = 1.0;
    utterance.volume = 1.0;
    
    // Get read feedback button
    const readFeedbackBtn = document.getElementById('readFeedbackBtn');
    
    // Events
    utterance.onstart = function() {
        console.log('Speech started');
        if (readFeedbackBtn) {
            readFeedbackBtn.classList.add('speaking');
            readFeedbackBtn.innerHTML = '<i class="bi bi-pause-fill me-1"></i> Pause';
        }
    };
    
    utterance.onend = function() {
        console.log('Speech ended');
        if (readFeedbackBtn) {
            readFeedbackBtn.classList.remove('speaking');
            readFeedbackBtn.innerHTML = '<i class="bi bi-volume-up me-1"></i> Read Feedback Aloud';
        }
    };
    
    utterance.onerror = function(event) {
        console.error('Speech synthesis error', event);
        if (readFeedbackBtn) {
            readFeedbackBtn.classList.remove('speaking');
            readFeedbackBtn.innerHTML = '<i class="bi bi-volume-up me-1"></i> Read Feedback Aloud';
        }
    };
    
    // Toggle between play and pause
    if (window.speechSynthesis.speaking) {
        if (window.speechSynthesis.paused) {
            window.speechSynthesis.resume();
            if (readFeedbackBtn) {
                readFeedbackBtn.innerHTML = '<i class="bi bi-pause-fill me-1"></i> Pause';
                readFeedbackBtn.classList.add('speaking');
            }
        } else {
            window.speechSynthesis.pause();
            if (readFeedbackBtn) {
                readFeedbackBtn.innerHTML = '<i class="bi bi-play-fill me-1"></i> Resume';
                readFeedbackBtn.classList.remove('speaking');
            }
        }
    } else {
        // Start new speech
        console.log('Starting speech synthesis');
        window.speechSynthesis.speak(utterance);
    }
}

/**
 * Stop any ongoing text-to-speech
 */
function stopSpeech() {
    if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel();
        
        const readFeedbackBtn = document.getElementById('readFeedbackBtn');
        if (readFeedbackBtn) {
            readFeedbackBtn.classList.remove('speaking');
            readFeedbackBtn.innerHTML = '<i class="bi bi-volume-up me-1"></i> Read Feedback Aloud';
        }
    }
}

// Stop speech when navigating away
window.addEventListener('beforeunload', function() {
    stopSpeech();
});

/**
 * Get the best available voice for TTS
 */
function getBestVoice(lang = 'en-US') {
    const voices = window.speechSynthesis.getVoices();
    
    // Look for premium voices first
    const premiumKeywords = ['premium', 'enhanced', 'neural', 'wavenet'];
    
    for (const keyword of premiumKeywords) {
        const premiumVoice = voices.find(voice => 
            voice.name.toLowerCase().includes(keyword)
        );
        if (premiumVoice) return premiumVoice;
    }
    
    // Fall back to first available voice
    return voices[0];
}