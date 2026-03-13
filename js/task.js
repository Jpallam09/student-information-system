/**
 * TASK MANAGER APPLICATION - CLIENT-SIDE LOGIC
 * @version 2.0.0
 */

// Global variable to store task being deleted
let taskToDelete = null;

// ==================== MODAL CONTROLLERS ====================

/**
 * Opens the task submission modal for a specific task type
 * @param {string} taskType - The type of task ('activities', 'homework', or 'laboratory')
 * @param {object} taskData - Optional task data for editing
 */
function openModal(taskType, taskData = null) {
    console.log("Opening modal for task type:", taskType);
    
    // Reset input states
    const titleInput = document.getElementById('taskTitle');
    const descriptionInput = document.getElementById('taskDescription');
    const attachmentInput = document.getElementById('taskAttachment');
    const submitBtn = document.querySelector('.submit-btn');
    const modalTitle = document.getElementById('modalTitle');
    
    titleInput.disabled = false;
    descriptionInput.disabled = false;
    attachmentInput.disabled = false;
    attachmentInput.style.display = 'flex';
    submitBtn.style.display = 'flex';
    submitBtn.disabled = false;
    
    const taskTypeInput = document.getElementById('taskType');
    const taskIdInput = document.getElementById('taskId');
    const currentAttachment = document.getElementById('currentAttachment');
    
    if (taskTypeInput) {
        taskTypeInput.value = taskType;
    }
    
    if (taskData) {
        // Edit mode
        taskIdInput.value = taskData.id;
        titleInput.value = taskData.title;
        descriptionInput.value = taskData.description;
        modalTitle.textContent = `✏️ Edit ${taskType} Task`;
        submitBtn.textContent = '✓ UPDATE TASK';
        
        if (taskData.attachment) {
            currentAttachment.style.display = 'flex';
            currentAttachment.innerHTML = `
                <i class="fas fa-paperclip"></i> 
                Current file: ${escapeHtml(taskData.original_filename || taskData.attachment)}
            `;
        } else {
            currentAttachment.style.display = 'none';
        }
    } else {
        // Add mode
        taskIdInput.value = '';
        titleInput.value = '';
        descriptionInput.value = '';
        attachmentInput.value = '';
        currentAttachment.style.display = 'none';
        const formattedType = taskType.charAt(0).toUpperCase() + taskType.slice(1);
        modalTitle.textContent = `➕ Add New ${formattedType} Task`;
        submitBtn.textContent = '✓ SUBMIT TASK';
    }
    
    const modal = document.getElementById('taskModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

/**
 * Closes the task submission modal and resets the form
 */
function closeModal() {
    const modal = document.getElementById('taskModal');
    if (modal) {
        modal.style.display = 'none';
    }
    
    const form = document.getElementById('taskForm');
    if (form) {
        form.reset();
    }
    
    // Re-enable all inputs and show submit button
    const titleInput = document.getElementById('taskTitle');
    const descriptionInput = document.getElementById('taskDescription');
    const attachmentInput = document.getElementById('taskAttachment');
    const submitBtn = document.querySelector('.submit-btn');
    
    titleInput.disabled = false;
    descriptionInput.disabled = false;
    attachmentInput.disabled = false;
    attachmentInput.style.display = 'flex';
    submitBtn.style.display = 'flex';
    submitBtn.disabled = false;
    
    document.getElementById('currentAttachment').style.display = 'none';
    document.body.style.overflow = 'auto';
}

/**
 * Opens delete confirmation modal
 */
function openDeleteModal(taskId, taskElement) {
    taskToDelete = { id: taskId, element: taskElement };
    const modal = document.getElementById('deleteModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

/**
 * Closes delete confirmation modal
 */
function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    if (modal) {
        modal.style.display = 'none';
    }
    taskToDelete = null;
    document.body.style.overflow = 'auto';
}

// Close modals when clicking outside
window.onclick = function(event) {
    const taskModal = document.getElementById('taskModal');
    const deleteModal = document.getElementById('deleteModal');
    if (event.target == taskModal) {
        closeModal();
    }
    if (event.target == deleteModal) {
        closeDeleteModal();
    }
}

// ==================== TASK CRUD OPERATIONS ====================

/**
 * Handles form submission for new/edited tasks
 */
async function submitTask(event) {
    event.preventDefault();
    
    const formData = new FormData(document.getElementById('taskForm'));
    const taskId = document.getElementById('taskId').value;
    
    const submitBtn = document.querySelector('.submit-btn');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = taskId ? '⏳ UPDATING...' : '⏳ SUBMITTING...';
    submitBtn.disabled = true;
    
    try {
        const url = taskId ? '../task/update_task.php' : '../task/submit_task.php';
        const response = await fetch(url, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        console.log("Submit response:", data);
        
        if (data.success) {
            if (taskId) {
                updateTaskInCard(data.task);
                showNotification('✅ Task updated successfully!', 'success');
            } else {
                addTaskToCard(data.task);
                showNotification('✅ Task submitted successfully!', 'success');
            }
            closeModal();
        } else {
            showNotification('❌ Error: ' + (data.message || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Submission error:', error);
        showNotification('❌ Error submitting task. Please try again.', 'error');
    } finally {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
}

/**
 * Handles task deletion
 */
async function confirmDelete() {
    if (!taskToDelete) return;
    
    try {
        const response = await fetch('../task/delete_task.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: taskToDelete.id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            taskToDelete.element.remove();
            showNotification('✅ Task deleted successfully!', 'success');
            
            // Check if card is empty and show "no tasks" message
            const tasksList = taskToDelete.element.closest('.tasks-list');
            if (tasksList && tasksList.querySelectorAll('.task-item').length === 0) {
                const noTasksMsg = document.createElement('div');
                noTasksMsg.className = 'no-tasks';
                noTasksMsg.innerHTML = '📭 No tasks submitted yet<br><small>Click Add Task to create one</small>';
                tasksList.appendChild(noTasksMsg);
            }
        } else {
            showNotification('❌ Error: ' + (data.message || 'Failed to delete task'), 'error');
        }
    } catch (error) {
        console.error('Delete error:', error);
        showNotification('❌ Error deleting task. Please try again.', 'error');
    } finally {
        closeDeleteModal();
    }
}

/**
 * View task details in a modal
 */
async function viewTask(taskId) {
    try {
        console.log("Viewing task ID:", taskId);
        
        const response = await fetch(`../task/get_task.php?id=${taskId}`);
        const data = await response.json();
        
        console.log("View task response:", data);
        
        if (data.success) {
            const task = data.task;
            
            // Set values for viewing
            document.getElementById('modalTitle').textContent = `👁️ View Task`;
            document.getElementById('taskType').value = task.task_type;
            document.getElementById('taskId').value = task.id;
            document.getElementById('taskTitle').value = task.title;
            document.getElementById('taskDescription').value = task.description;
            
            // Disable inputs for view mode
            document.getElementById('taskTitle').disabled = true;
            document.getElementById('taskDescription').disabled = true;
            document.getElementById('taskAttachment').disabled = true;
            document.getElementById('taskAttachment').style.display = 'none';
            
            // Hide submit button in view mode
            document.querySelector('.submit-btn').style.display = 'none';
            
            // Show current attachment if exists
            const currentAttachment = document.getElementById('currentAttachment');
            if (task.attachment) {
                currentAttachment.style.display = 'flex';
                currentAttachment.innerHTML = `
                    <i class="fas fa-paperclip"></i> 
                    <a href="uploads/${encodeURIComponent(task.attachment)}" target="_blank">
                        ${escapeHtml(task.original_filename || task.attachment)}
                    </a>
                `;
            } else {
                currentAttachment.style.display = 'none';
            }
            
            // Show modal
            const modal = document.getElementById('taskModal');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
        } else {
            alert('Failed to load task details: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error viewing task:', error);
        alert('Error loading task details. Please check console for details.');
    }
}

/**
 * Edit task
 */
async function editTask(taskId) {
    try {
        console.log("Editing task ID:", taskId);
        
        const response = await fetch(`../task/get_task.php?id=${taskId}`);
        const data = await response.json();
        
        console.log("Edit task response:", data);
        
        if (data.success) {
            const task = data.task;
            
            // Enable all inputs for editing
            document.getElementById('taskTitle').disabled = false;
            document.getElementById('taskDescription').disabled = false;
            document.getElementById('taskAttachment').disabled = false;
            document.getElementById('taskAttachment').style.display = 'flex';
            document.querySelector('.submit-btn').style.display = 'flex';
            document.querySelector('.submit-btn').disabled = false;
            
            // Open modal with task data
            openModal(task.task_type, task);
            
        } else {
            alert('Failed to load task for editing: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error loading task for edit:', error);
        alert('Error loading task for editing. Please check console for details.');
    }
}

/**
 * Delete task with confirmation
 */
function deleteTask(taskId, button) {
    const taskElement = button.closest('.task-item');
    openDeleteModal(taskId, taskElement);
}

/**
 * Creates and adds a task element to the appropriate card
 */
function addTaskToCard(task) {
    console.log("Adding task to card:", task);
    
    // Check both possible field names (type or task_type)
    const taskType = task.task_type || task.type;
    const taskId = task.id;
    const taskTitle = task.title;
    const taskDescription = task.description;
    const taskAttachment = task.attachment;
    const taskOriginalFilename = task.original_filename;
    const taskCreatedAt = task.created_at;
    
    if (!taskType || !taskId) {
        console.error('Invalid task object:', task);
        return;
    }
    
    const tasksContainer = document.getElementById(`${taskType}-tasks`);
    if (!tasksContainer) {
        console.error(`Container for ${taskType} not found`);
        return;
    }
    
    // Remove "no tasks" message if it exists
    const noTasksMsg = tasksContainer.querySelector('.no-tasks');
    if (noTasksMsg) {
        noTasksMsg.remove();
    }
    
    const taskElement = document.createElement('article');
    taskElement.className = 'task-item';
    taskElement.setAttribute('data-task-id', taskId);
    taskElement.setAttribute('aria-label', `Task: ${taskTitle}`);
    
    let dateStr = 'Date not available';
    try {
        if (taskCreatedAt) {
            const taskDate = new Date(taskCreatedAt);
            if (!isNaN(taskDate.getTime())) {
                dateStr = taskDate.toLocaleDateString() + ' ' + taskDate.toLocaleTimeString();
            }
        }
    } catch (e) {
        console.warn('Date parsing error:', e);
    }
    
    let taskHTML = `
        <header>
            <h4>${escapeHtml(taskTitle)}</h4>
        </header>
        <main>
            <p>${escapeHtml(taskDescription)}</p>
        </main>
        <footer>
            <small>📅 Submitted: ${dateStr}</small>
    `;
    
    if (taskAttachment) {
        const displayName = taskOriginalFilename || taskAttachment;
        const shortDisplayName = truncateFilename(displayName, 22);
        const fileIcon = getFileIcon(displayName);
        
        taskHTML += `
            <div class="task-attachment" title="${escapeHtml(displayName)}">
                <i>${fileIcon}</i> 
                <a href="uploads/${encodeURIComponent(taskAttachment)}" 
                   target="_blank" 
                   rel="noopener noreferrer">
                    ${escapeHtml(shortDisplayName)}
                </a>
            </div>
        `;
    }
    
    taskHTML += `
            <div class="task-actions">
                <button class="action-btn view-btn" onclick="viewTask(${taskId})" title="View">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="action-btn edit-btn" onclick="editTask(${taskId})" title="Edit">
                    <i class="fas fa-pencil-alt"></i>
                </button>
                <button class="action-btn delete-btn" onclick="deleteTask(${taskId}, this)" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </footer>
    `;
    
    taskElement.innerHTML = taskHTML;
    
    // Find the heading and insert after it
    const heading = tasksContainer.querySelector('h3');
    if (heading) {
        if (heading.nextSibling) {
            tasksContainer.insertBefore(taskElement, heading.nextSibling);
        } else {
            tasksContainer.appendChild(taskElement);
        }
    } else {
        tasksContainer.appendChild(taskElement);
    }
    
    taskElement.style.animation = 'slideIn 0.3s ease';
}

/**
 * Updates an existing task in the card
 */
function updateTaskInCard(task) {
    const existingTask = document.querySelector(`.task-item[data-task-id="${task.id}"]`);
    if (existingTask) {
        const newTaskElement = createTaskElement(task);
        existingTask.parentNode.replaceChild(newTaskElement, existingTask);
    }
}

/**
 * Creates a task element with view, edit, delete icons
 */
function createTaskElement(task) {
    const taskType = task.task_type || task.type;
    const taskId = task.id;
    const taskTitle = task.title;
    const taskDescription = task.description;
    const taskAttachment = task.attachment;
    const taskOriginalFilename = task.original_filename;
    const taskCreatedAt = task.created_at;
    
    const taskElement = document.createElement('article');
    taskElement.className = 'task-item';
    taskElement.setAttribute('data-task-id', taskId);
    taskElement.setAttribute('aria-label', `Task: ${taskTitle}`);
    
    let dateStr = 'Date not available';
    try {
        if (taskCreatedAt) {
            const taskDate = new Date(taskCreatedAt);
            if (!isNaN(taskDate.getTime())) {
                dateStr = taskDate.toLocaleDateString() + ' ' + taskDate.toLocaleTimeString();
            }
        }
    } catch (e) {
        console.warn('Date parsing error:', e);
    }
    
    let taskHTML = `
        <header>
            <h4>${escapeHtml(taskTitle)}</h4>
        </header>
        <main>
            <p>${escapeHtml(taskDescription)}</p>
        </main>
        <footer>
            <small>📅 Submitted: ${dateStr}</small>
    `;
    
    if (taskAttachment) {
        const displayName = taskOriginalFilename || taskAttachment;
        const shortDisplayName = truncateFilename(displayName, 22);
        const fileIcon = getFileIcon(displayName);
        
        taskHTML += `
            <div class="task-attachment" title="${escapeHtml(displayName)}">
                <i>${fileIcon}</i> 
                <a href="uploads/${encodeURIComponent(taskAttachment)}" 
                   target="_blank" 
                   rel="noopener noreferrer">
                    ${escapeHtml(shortDisplayName)}
                </a>
            </div>
        `;
    }
    
    taskHTML += `
            <div class="task-actions">
                <button class="action-btn view-btn" onclick="viewTask(${taskId})" title="View">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="action-btn edit-btn" onclick="editTask(${taskId})" title="Edit">
                    <i class="fas fa-pencil-alt"></i>
                </button>
                <button class="action-btn delete-btn" onclick="deleteTask(${taskId}, this)" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </footer>
    `;
    
    taskElement.innerHTML = taskHTML;
    return taskElement;
}

/**
 * Loads tasks for a specific card from the server
 */
async function loadTasksForCard(taskType) {
    const tasksContainer = document.getElementById(`${taskType}-tasks`);
    if (!tasksContainer) {
        console.error(`Container for ${taskType} not found`);
        return;
    }
    
    // Clear everything but keep the heading
    const heading = tasksContainer.querySelector('h3');
    tasksContainer.innerHTML = '';
    if (heading) {
        tasksContainer.appendChild(heading);
    } else {
        const newHeading = document.createElement('h3');
        newHeading.textContent = '📋 SUBMITTED TASKS';
        tasksContainer.appendChild(newHeading);
    }
    
    const loadingMsg = document.createElement('div');
    loadingMsg.className = 'loading-tasks';
    loadingMsg.textContent = '⏳ Loading tasks...';
    tasksContainer.appendChild(loadingMsg);
    
try {
        const response = await fetch(`../task/get_task.php?type=${encodeURIComponent(taskType)}`);
        const data = await response.json();
        
        loadingMsg.remove();
        
        if (data.success) {
            if (data.tasks.length === 0) {
                const noTasksMsg = document.createElement('div');
                noTasksMsg.className = 'no-tasks';
                noTasksMsg.innerHTML = '📭 No tasks submitted yet<br><small>Click Add Task to create one</small>';
                tasksContainer.appendChild(noTasksMsg);
            } else {
                data.tasks.forEach(task => {
                    addTaskToCard(task);
                });
            }
        } else {
            const noTasksMsg = document.createElement('div');
            noTasksMsg.className = 'no-tasks';
            noTasksMsg.innerHTML = '📭 No tasks available';
            tasksContainer.appendChild(noTasksMsg);
        }
    } catch (error) {
        console.error('Error loading tasks:', error);
        loadingMsg.remove();
        
        const noTasksMsg = document.createElement('div');
        noTasksMsg.className = 'no-tasks';
        noTasksMsg.innerHTML = '📭 No tasks available';
        tasksContainer.appendChild(noTasksMsg);
    }
}

// ==================== UTILITY FUNCTIONS ====================

/**
 * Truncates long filenames for display while preserving extension
 */
function truncateFilename(filename, maxLength = 25) {
    if (!filename || typeof filename !== 'string') return '';
    if (filename.length <= maxLength) return filename;
    
    const lastDotIndex = filename.lastIndexOf('.');
    if (lastDotIndex === -1) {
        return filename.substring(0, maxLength - 3) + '...';
    }
    
    const extension = filename.substring(lastDotIndex + 1);
    const nameWithoutExt = filename.substring(0, lastDotIndex);
    
    if (nameWithoutExt.length <= 10) {
        return filename.substring(0, maxLength - 3) + '...';
    }
    
    const availableLength = maxLength - extension.length - 4;
    const truncatedName = nameWithoutExt.substring(0, Math.max(availableLength, 5)) + '...';
    
    return truncatedName + '.' + extension;
}

/**
 * Returns appropriate emoji icon based on file type
 */
function getFileIcon(filename) {
    if (!filename || typeof filename !== 'string') return '📎';
    
    const ext = filename.split('.').pop().toLowerCase();
    
    const icons = {
        pdf: '📕', doc: '📘', docx: '📘', txt: '📄',
        xls: '📗', xlsx: '📗',
        ppt: '📙', pptx: '📙',
        jpg: '🖼️', jpeg: '🖼️', png: '🖼️', gif: '🎨',
        zip: '📦', rar: '📦',
        js: '⚙️', html: '🌐', css: '🎨', php: '⚙️'
    };
    
    return icons[ext] || '📎';
}

/**
 * Simple HTML escaping to prevent XSS attacks
 */
function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * Shows a temporary notification to the user
 */
function showNotification(message, type = 'info') {
    const existing = document.querySelector('.notification');
    if (existing) existing.remove();
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.setAttribute('role', 'alert');
    
    document.body.appendChild(notification);
    
    setTimeout(() => notification.classList.add('show'), 10);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// ==================== INITIALIZATION ====================

document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Task Manager initialized successfully');
    
    // Load all tasks
    loadTasksForCard('activities');
    loadTasksForCard('homework');
    loadTasksForCard('laboratory');
    
    // Add keyboard support
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal();
            closeDeleteModal();
        }
    });
});