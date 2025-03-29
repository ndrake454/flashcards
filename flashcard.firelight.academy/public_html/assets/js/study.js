/**
 * Study page specific JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize study interface
    initStudyInterface();
    
    // Add event listeners for response submission
    const submitBtn = document.getElementById('submitResponse');
    if (submitBtn) {
        submitBtn.addEventListener('click', handleSubmitResponse);
    }
    
    // Add keyboard shortcut (Ctrl+Enter or Cmd+Enter) to submit
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            if (!document.getElementById('responseControls').classList.contains('d-none')) {
                handleSubmitResponse();
            } else if (!document.getElementById('nextControls').classList.contains('d-none')) {
                loadNextCard();
            }
        }
    });
    
    // Auto resize textarea as user types
    const textarea = document.getElementById('userResponse');
    if (textarea) {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    }
});

/**
 * Initialize the study interface
 */
function initStudyInterface() {
    const categoryId = document.getElementById('categoryId')?.value;
    
    if (categoryId) {
        // If a category is selected, load the first card
        loadNextCard();
    }
}

/**
 * Handle the submit response button click
 */
function handleSubmitResponse() {
    const responseInput = document.getElementById('userResponse');
    const response = responseInput.value.trim();
    
    // Validate response
    if (!response) {
        // Show validation error
        responseInput.classList.add('is-invalid');
        
        // Create error message if it doesn't exist
        let errorMsg = document.getElementById('responseError');
        if (!errorMsg) {
            errorMsg = document.createElement('div');
            errorMsg.id = 'responseError';
            errorMsg.className = 'invalid-feedback';
            errorMsg.textContent = 'Please enter your response before submitting.';
            responseInput.parentNode.appendChild(errorMsg);
        }
        
        // Focus the input
        responseInput.focus();
        return;
    }
    
    // Remove validation error if exists
    responseInput.classList.remove('is-invalid');
    
    // Show loading state
    const submitBtn = document.getElementById('submitResponse');
    const loadingSpinner = document.getElementById('responseLoading');
    
    submitBtn.disabled = true;
    loadingSpinner.classList.remove('d-none');
    
    // Get form data
    const cardId = document.getElementById('currentCardId').value;
    const startTime = parseInt(document.getElementById('startTime').value);
    const endTime = Math.floor(Date.now() / 1000);
    const responseTime = endTime - startTime;
    
    // Submit response to the server
    submitUserResponse(cardId, response, responseTime)
        .then(handleResponseSubmissionResult)
        .catch(handleResponseSubmissionError)
        .finally(() => {
            // Reset button state
            submitBtn.disabled = false;
            loadingSpinner.classList.add('d-none');
        });
}

/**
 * Submit the user's response to the server
 */
async function submitUserResponse(cardId, response, responseTime) {
    try {
        const resp = await fetch('api/progress.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'submit_response',
                card_id: cardId,
                response: response,
                response_time: responseTime
            })
        });
        
        if (!resp.ok) {
            throw new Error(`Server responded with status: ${resp.status}`);
        }
        
        return await resp.json();
    } catch (error) {
        console.error('Error submitting response:', error);
        throw error;
    }
}

/**
 * Handle the result of submitting a response
 */
function handleResponseSubmissionResult(data) {
    if (data.success) {
        // Show feedback
        displayFeedback(data.evaluation);
        
        // Update UI to show next button
        document.getElementById('responseControls').classList.add('d-none');
        document.getElementById('nextControls').classList.remove('d-none');
        
        // Focus the next button
        document.getElementById('nextControls').querySelector('button').focus();
        
        // Show next review date if available
        if (data.next_review) {
            const nextReviewDate = new Date(data.next_review);
            const today = new Date();
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            
            let reviewMessage = '';
            
            if (nextReviewDate.toDateString() === today.toDateString()) {
                reviewMessage = 'You will review this card again today.';
            } else if (nextReviewDate.toDateString() === tomorrow.toDateString()) {
                reviewMessage = 'You will review this card again tomorrow.';
            } else {
                reviewMessage = `You will review this card again on ${nextReviewDate.toLocaleDateString()}.`;
            }
            
            // Add review message to feedback
            const reviewNotice = document.createElement('p');
            reviewNotice.className = 'text-muted mt-3';
            reviewNotice.textContent = reviewMessage;
            document.getElementById('feedbackContainer').querySelector('.card-body').appendChild(reviewNotice);
        }
    } else {
        // Show error
        showError('Failed to evaluate response: ' + data.message);
    }
}

/**
 * Handle errors when submitting a response
 */
function handleResponseSubmissionError(error) {
    console.error('Response submission error:', error);
    showError('An error occurred while submitting your response. Please try again.');
}

/**
 * Display feedback from the AI evaluation
 */
function displayFeedback(evaluation) {
    const feedbackContainer = document.getElementById('feedbackContainer');
    
    // Create feedback elements
    feedbackContainer.innerHTML = '';
    
    const feedbackCard = document.createElement('div');
    feedbackCard.className = 'card my-3';
    
    // Create header with appropriate color based on understanding
    const cardHeader = document.createElement('div');
    cardHeader.className = evaluation.understood ? 
        'card-header bg-success text-white' : 
        'card-header bg-warning';
    
    // Create header content
    const headerText = document.createElement('h5');
    headerText.className = 'mb-0';
    headerText.textContent = evaluation.understood ? 
        '✓ Good job! You understand this concept.' : 
        '⚠ You might need to review this concept more.';
    cardHeader.appendChild(headerText);
    
    // Create card body
    const cardBody = document.createElement('div');
    cardBody.className = 'card-body';
    
    // Add feedback text
    const feedbackText = document.createElement('p');
    feedbackText.className = 'card-text';
    feedbackText.textContent = evaluation.feedback;
    cardBody.appendChild(feedbackText);
    
    // If there are missing points, add them
    if (evaluation.missing_points && evaluation.missing_points.length > 0) {
        const missingPointsTitle = document.createElement('h6');
        missingPointsTitle.className = 'mt-4 mb-2';
        missingPointsTitle.textContent = 'Key points to remember:';
        cardBody.appendChild(missingPointsTitle);
        
        const missingPointsList = document.createElement('ul');
        missingPointsList.className = 'missing-points';
        
        evaluation.missing_points.forEach(point => {
            const listItem = document.createElement('li');
            listItem.textContent = point;
            missingPointsList.appendChild(listItem);
        });
        
        cardBody.appendChild(missingPointsList);
    }
    
    // Assemble the card
    feedbackCard.appendChild(cardHeader);
    feedbackCard.appendChild(cardBody);
    feedbackContainer.appendChild(feedbackCard);
    
    // Show the container
    feedbackContainer.classList.remove('d-none');
    
    // Scroll to feedback with smooth animation
    feedbackContainer.scrollIntoView({ 
        behavior: 'smooth', 
        block: 'start'
    });
    
    // Dispatch event to notify speech features
    document.dispatchEvent(new CustomEvent('feedbackDisplayed', {
        detail: { evaluation: evaluation }
    }));
}

/**
 * Load the next flashcard
 */
function loadNextCard() {
    // Stop any ongoing speech
    if (window.stopSpeech) {
        window.stopSpeech();
    }
    
    const categoryId = document.getElementById('categoryId')?.value || '';
    const cardLoading = document.getElementById('cardLoading');
    
    // Show loading state
    cardLoading.classList.remove('d-none');
    document.getElementById('questionText').textContent = '';
    document.getElementById('feedbackContainer').classList.add('d-none');
    
    // Hide controls while loading
    document.getElementById('responseControls').classList.add('d-none');
    document.getElementById('nextControls').classList.add('d-none');
    
    // Fetch the next card
    fetch(`api/cards.php?action=get_next_card&category_id=${categoryId}`)
        .then(response => response.json())
        .then(data => {
            // Hide loading state
            cardLoading.classList.add('d-none');
            
            if (data.success) {
                // Update the UI with the new card
                updateCardUI(data.card);
            } else {
                // No more cards or error
                handleNoMoreCards(data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching next card:', error);
            cardLoading.classList.add('d-none');
            showError('Failed to load the next card. Please try again.');
        });
}

/**
 * Update the UI with a new flashcard
 */
function updateCardUI(card) {
    // Update question text with fade animation
    const questionText = document.getElementById('questionText');
    questionText.style.opacity = '0';
    
    setTimeout(() => {
        questionText.textContent = card.question;
        questionText.style.opacity = '1';
    }, 300);
    
    // Update hidden fields
    document.getElementById('currentCardId').value = card.card_id;
    document.getElementById('startTime').value = Math.floor(Date.now() / 1000);
    
    // Clear previous response
    const userResponse = document.getElementById('userResponse');
    userResponse.value = '';
    userResponse.style.height = 'auto'; // Reset height if auto-resize is enabled
    
    // Clear previous feedback
    document.getElementById('feedbackContainer').innerHTML = '';
    document.getElementById('feedbackContainer').classList.add('d-none');
    
    // Show response controls, hide next controls
    document.getElementById('responseControls').classList.remove('d-none');
    document.getElementById('nextControls').classList.add('d-none');
    
    // Set focus to response input
    userResponse.focus();
    
    // Update progress indicator if available
    if (card.progress) {
        const progressIndicator = document.getElementById('progressIndicator');
        if (progressIndicator) {
            progressIndicator.textContent = `Card ${card.progress.current} of ${card.progress.total}`;
        }
    }
}

/**
 * Handle the case when there are no more cards
 */
function handleNoMoreCards(message) {
    // Display completion message
    const studyContainer = document.getElementById('studyContainer');
    
    studyContainer.innerHTML = `
        <div class="card text-center py-5 completion-card">
            <div class="card-body">
                <div class="completion-icon"><i class="bi bi-check-circle-fill"></i></div>
                <h3 class="mb-4">Study Session Complete!</h3>
                <p class="lead mb-4">${message}</p>
                <div class="d-grid gap-2 col-md-6 mx-auto">
                    <a href="categories.php" class="btn btn-primary btn-lg">Study Another Category</a>
                    <a href="index.php" class="btn btn-outline-secondary">Return Home</a>
                </div>
            </div>
        </div>
    `;
}

/**
 * Show an error message to the user
 */
function showError(message) {
    // Create error alert
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
    alertDiv.setAttribute('role', 'alert');
    
    alertDiv.innerHTML = `
        <strong>Error:</strong> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Add to the page
    const studyContainer = document.getElementById('studyContainer');
    studyContainer.insertBefore(alertDiv, studyContainer.firstChild);
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        const bsAlert = new bootstrap.Alert(alertDiv);
        bsAlert.close();
    }, 5000);
}