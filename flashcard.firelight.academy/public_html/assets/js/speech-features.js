/**
 * Speech Features for Flashcard App
 * Implements Speech-to-Text and Text-to-Speech functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Speech features initializing...');
    // Initialize speech features
    initSpeechFeatures();
});

/**
 * Initialize speech features
 */
function initSpeechFeatures() {
    // Initialize speech recognition
    initSpeechRecognition();
    
    // Initialize text-to-speech
    initTextToSpeech();
    
    // Add event listeners for feedback container
    attachFeedbackListener();
}

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
 * Initialize speech recognition
 */
function initSpeechRecognition() {
    console.log('Initializing speech recognition...');
    
    // Check if browser supports speech recognition
    if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
        console.error('Speech recognition not supported by this browser');
        document.getElementById('micButton')?.classList.add('d-none');
        return;
    }
    
    console.log('Speech recognition supported by browser');
    
    // Initialize speech recognition
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const recognition = new SpeechRecognition();
    
    // Configure recognition
    recognition.continuous = false;
    recognition.interimResults = true;
    recognition.lang = 'en-US'; // Default to English
    
    // Get UI elements
    const micButton = document.getElementById('micButton');
    const userResponse = document.getElementById('userResponse');
    const speechToast = document.getElementById('speechToast');
    const speechToastMessage = document.getElementById('speechToastMessage');
    
    // Create toast instance if bootstrap is available
    let speechToastInstance;
    if (typeof bootstrap !== 'undefined' && speechToast) {
        speechToastInstance = new bootstrap.Toast(speechToast, {
            autohide: false
        });
    }
    
    // Add event listener to mic button
    if (micButton) {
        console.log('Adding event listener to mic button');
        micButton.addEventListener('click', function() {
            console.log('Mic button clicked');
            if (micButton.classList.contains('listening')) {
                // Stop listening
                recognition.stop();
                console.log('Stopping speech recognition');
            } else {
                // Start listening
                console.log('Starting speech recognition');
                startListening();
            }
        });
    } else {
        console.error('Mic button not found');
    }
    
    // Speech recognition events
    recognition.onstart = function() {
        console.log('Speech recognition started');
        micButton.classList.add('listening');
        micButton.innerHTML = '<i class="bi bi-mic-fill"></i>';
        
        // Show toast
        if (speechToastMessage) {
            speechToastMessage.textContent = 'Listening...';
        }
        if (speechToastInstance) {
            speechToastInstance.show();
        } else if (speechToast) {
            speechToast.classList.add('show');
        }
    };
    
    recognition.onresult = function(event) {
        console.log('Speech recognition result received', event);
        let interimTranscript = '';
        let finalTranscript = '';
        
        for (let i = event.resultIndex; i < event.results.length; ++i) {
            if (event.results[i].isFinal) {
                finalTranscript += event.results[i][0].transcript;
            } else {
                interimTranscript += event.results[i][0].transcript;
            }
        }
        
        console.log('Interim transcript:', interimTranscript);
        console.log('Final transcript:', finalTranscript);
        
        // Update toast with interim results
        if (interimTranscript && speechToastMessage) {
            speechToastMessage.textContent = 'Listening: ' + interimTranscript;
        }
        
        // Add final transcript to textarea
        if (finalTranscript && userResponse) {
            // If textarea already has content, add a space
            if (userResponse.value && !userResponse.value.endsWith(' ')) {
                userResponse.value += ' ';
            }
            
            // Add transcribed text
            userResponse.value += finalTranscript;
            
            // Trigger input event to resize textarea if auto-resize is enabled
            userResponse.dispatchEvent(new Event('input'));
        }
    };
    
    recognition.onerror = function(event) {
        console.error('Speech recognition error', event);
        
        if (speechToastMessage) {
            if (event.error === 'not-allowed') {
                speechToastMessage.innerHTML = '<span class="text-danger">Microphone access denied. Check your browser permissions.</span>';
            } else {
                speechToastMessage.innerHTML = '<span class="text-danger">Error: ' + event.error + '</span>';
            }
        }
        
        setTimeout(() => {
            if (speechToastInstance) {
                speechToastInstance.hide();
            } else if (speechToast) {
                speechToast.classList.remove('show');
            }
        }, 3000);
        
        stopListening();
    };
    
    recognition.onend = function() {
        console.log('Speech recognition ended');
        stopListening();
    };
    
    /**
     * Start listening for speech
     */
    function startListening() {
        try {
            recognition.start();
            console.log('Recognition started successfully');
        } catch (e) {
            console.error('Failed to start speech recognition', e);
            if (speechToastMessage) {
                speechToastMessage.innerHTML = '<span class="text-danger">Failed to start microphone. Please try again.</span>';
            }
            stopListening();
        }
    }
    
    /**
     * Stop listening for speech
     */
    function stopListening() {
        if (micButton) {
            micButton.classList.remove('listening');
            micButton.innerHTML = '<i class="bi bi-mic"></i>';
        }
        
        // Hide toast after a delay
        setTimeout(() => {
            if (speechToastInstance) {
                speechToastInstance.hide();
            } else if (speechToast) {
                speechToast.classList.remove('show');
            }
        }, 1000);
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
    
    const feedbackTitle = feedbackCard.querySelector('.card-header').textContent;
    const feedbackText = feedbackCard.querySelector('.card-text').textContent;
    
    console.log('Feedback title:', feedbackTitle);
    console.log('Feedback text:', feedbackText);
    
    // Combine text to read
    let textToRead = feedbackTitle + '. ' + feedbackText;
    
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

function getBestVoice() {
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