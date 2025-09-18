/**
 * Event Management JavaScript
 * 
 * Handles event management interface including recurring events, 
 * holidays, and break scheduling.
 * 
 * @package RfidCheckin\Frontend
 * @author Kralder
 */

class EnhancedEventManager {
    constructor() {
        this.currentPage = 1;
        this.currentFilters = {};
        this.selectedWeekdays = [];
        this.selectedParticipants = [];
        this.breakSchedule = [];
        this.isEditMode = false;
        this.currentEventId = null;
    }

    // Initialize the event management system
    init() {
        this.setupEventListeners();
        this.loadEvents();
        this.loadEventStats();
        this.loadBreakTemplates();
    }

    // Set up event listeners
    setupEventListeners() {
        // Form submission
        document.getElementById('eventForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveEvent();
        });

        // Search and filters
        document.getElementById('searchInput').addEventListener('input', 
            this.debounce(() => this.loadEvents(), 500));
        document.getElementById('statusFilter').addEventListener('change', () => this.loadEvents());

        // Weekday selector
        document.querySelectorAll('.weekday-btn').forEach(btn => {
            btn.addEventListener('click', () => this.toggleWeekday(btn));
        });

        // Date change listeners for holiday checking
        document.getElementById('startDate').addEventListener('change', () => this.checkHolidayConflicts());
        document.getElementById('endDate').addEventListener('change', () => this.checkHolidayConflicts());
        document.getElementById('recurrenceType').addEventListener('change', () => {
            this.toggleRecurrenceOptions();
            this.checkHolidayConflicts();
        });
    }

    // Load and display events
    async loadEvents() {
        try {
            const filters = this.getCurrentFilters();
            const response = await this.apiCall('load_events', {
                ...filters,
                page: this.currentPage,
                limit: 25
            });

            if (response.success) {
                this.displayEvents(response.events);
                this.updatePagination(response.pagination);
            } else {
                this.showError(response.error || 'Failed to load events');
            }
        } catch (error) {
            console.error('Error loading events:', error);
            this.showError('Failed to load events');
        }
    }

    // Get current filter values
    getCurrentFilters() {
        return {
            search: document.getElementById('searchInput').value.trim(),
            status: document.getElementById('statusFilter').value
        };
    }

    // Display events in the table
    displayEvents(events) {
        const tbody = document.getElementById('eventsTableBody');
        
        if (events.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center">
                        <div class="empty-state">
                            <div class="empty-state-icon">📅</div>
                            <h4>No Events Found</h4>
                            <p>Create your first event to get started.</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = events.map(event => `
            <tr>
                <td>
                    <div class="event-info">
                        <h5>${this.escapeHtml(event.name)}</h5>
                        <small class="text-muted">${this.escapeHtml(event.location || 'No location')}</small>
                        ${event.is_recurring ? '<span class="badge badge-info">Recurring</span>' : ''}
                        ${event.has_breaks ? '<span class="badge badge-warning">Has Breaks</span>' : ''}
                    </div>
                </td>
                <td>
                    <span class="badge badge-secondary">${event.event_type}</span>
                </td>
                <td>
                    <div class="schedule-info">
                        ${this.formatEventSchedule(event)}
                        ${event.holiday_conflicts > 0 ? 
                            `<div class="text-warning small">⚠️ ${event.holiday_conflicts} holiday conflicts</div>` : ''
                        }
                    </div>
                </td>
                <td>
                    <div class="participant-info">
                        <span class="participant-count">${event.registered_count || 0}</span>
                        ${event.capacity ? `/ ${event.capacity}` : ''}
                        <small class="text-muted d-block">registered</small>
                    </div>
                </td>
                <td>
                    ${this.getEventStatusBadge(event)}
                    ${event.is_recurring ? 
                        `<small class="d-block">${event.future_instances} upcoming</small>` : ''
                    }
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-primary" onclick="eventManager.editEvent(${event.event_id})" title="Edit">
                            ✏️
                        </button>
                        <button class="btn btn-info" onclick="eventManager.viewEventDetails(${event.event_id})" title="Details">
                            👁️
                        </button>
                        ${event.is_recurring ? 
                            `<button class="btn btn-warning" onclick="eventManager.manageInstances(${event.event_id})" title="Manage Instances">📅</button>` : ''
                        }
                        <button class="btn btn-danger" onclick="eventManager.deleteEvent(${event.event_id})" title="Delete">
                            🗑️
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    // Format event schedule display
    formatEventSchedule(event) {
        let schedule = '';
        
        if (event.is_recurring) {
            schedule += `<strong>${event.recurrence_type}</strong><br>`;
            if (event.recurrence_days) {
                const days = JSON.parse(event.recurrence_days);
                const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                schedule += `${days.map(d => dayNames[d]).join(', ')}<br>`;
            }
        }
        
        schedule += `${event.start_date}`;
        if (event.end_date && event.end_date !== event.start_date) {
            schedule += ` - ${event.end_date}`;
        }
        
        if (event.start_time) {
            schedule += `<br>${event.start_time}`;
            if (event.end_time) {
                schedule += ` - ${event.end_time}`;
            }
        }
        
        return schedule;
    }

    // Get event status badge
    getEventStatusBadge(event) {
        const now = new Date();
        const startDate = new Date(event.start_date);
        const endDate = event.end_date ? new Date(event.end_date) : null;
        
        if (event.is_recurring) {
            if (event.recurrence_end_date && new Date(event.recurrence_end_date) < now) {
                return '<span class="badge badge-secondary">Completed</span>';
            }
            return '<span class="badge badge-success">Active</span>';
        } else {
            if (endDate && endDate < now) {
                return '<span class="badge badge-secondary">Past</span>';
            } else if (startDate <= now && (!endDate || endDate >= now)) {
                return '<span class="badge badge-success">Current</span>';
            } else {
                return '<span class="badge badge-primary">Upcoming</span>';
            }
        }
    }

    // Load event statistics
    async loadEventStats() {
        try {
            const response = await this.apiCall('load_events', { limit: 1000 });
            
            if (response.success) {
                const events = response.events;
                const stats = this.calculateStats(events);
                this.updateStatsDisplay(stats);
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    // Calculate event statistics
    calculateStats(events) {
        const now = new Date();
        
        return {
            total: events.length,
            active: events.filter(e => e.active).length,
            recurring: events.filter(e => e.is_recurring).length,
            upcoming: events.filter(e => {
                if (e.is_recurring) {
                    return !e.recurrence_end_date || new Date(e.recurrence_end_date) > now;
                }
                return new Date(e.start_date) > now;
            }).length
        };
    }

    // Update statistics display
    updateStatsDisplay(stats) {
        document.getElementById('totalEvents').textContent = stats.total;
        document.getElementById('activeEvents').textContent = stats.active;
        document.getElementById('recurringEvents').textContent = stats.recurring;
        document.getElementById('upcomingInstances').textContent = stats.upcoming;
    }

    // Show create event modal
    showCreateEventModal() {
        this.isEditMode = false;
        this.currentEventId = null;
        this.resetForm();
        document.getElementById('eventModalTitle').textContent = 'Create Event';
        this.showModal('eventModal');
    }

    // Edit existing event
    async editEvent(eventId) {
        try {
            const response = await this.apiCall('load_event_details', { event_id: eventId });
            
            if (response.success) {
                this.isEditMode = true;
                this.currentEventId = eventId;
                this.populateForm(response.event);
                document.getElementById('eventModalTitle').textContent = 'Edit Event';
                this.showModal('eventModal');
            } else {
                this.showError(response.error || 'Failed to load event details');
            }
        } catch (error) {
            console.error('Error loading event:', error);
            this.showError('Failed to load event details');
        }
    }

    // Populate form with event data
    populateForm(event) {
        document.getElementById('eventId').value = event.event_id;
        document.getElementById('eventName').value = event.name || '';
        document.getElementById('eventDescription').value = event.description || '';
        document.getElementById('eventLocation').value = event.location || '';
        document.getElementById('eventType').value = event.event_type || 'general';
        document.getElementById('eventCapacity').value = event.capacity || '';
        
        document.getElementById('startDate').value = event.start_date || '';
        document.getElementById('endDate').value = event.end_date || '';
        document.getElementById('startTime').value = event.start_time || '';
        document.getElementById('endTime').value = event.end_time || '';
        
        // Recurring settings
        const recurrenceType = event.is_recurring ? (event.recurrence_type || 'weekly') : 'one_time';
        document.getElementById('recurrenceType').value = recurrenceType;
        document.getElementById('recurrenceInterval').value = event.recurrence_interval || 1;
        document.getElementById('recurrenceEndDate').value = event.recurrence_end_date || '';
        document.getElementById('maxOccurrences').value = event.max_occurrences || '';
        document.getElementById('excludeHolidays').checked = event.exclude_holidays !== false;
        
        // Weekdays for weekly recurrence
        if (event.recurrence_days) {
            const days = JSON.parse(event.recurrence_days);
            this.selectedWeekdays = days;
            this.updateWeekdayButtons();
        }
        
        // Break settings
        document.getElementById('hasBreaks').checked = event.has_breaks || false;
        if (event.break_schedule) {
            this.breakSchedule = JSON.parse(event.break_schedule);
            this.populateBreakSchedule();
        }
        
        // Advanced settings
        document.getElementById('requireCheckin').checked = event.require_checkin !== false;
        document.getElementById('allowManualCheckin').checked = event.allow_manual_checkin !== false;
        document.getElementById('autoCheckout').checked = event.auto_checkout || false;
        document.getElementById('autoCheckoutMinutes').value = event.auto_checkout_minutes || 480;
        
        // Trigger UI updates
        this.toggleRecurrenceOptions();
        this.toggleBreakSchedule();
        this.toggleAutoCheckout();
    }

    // Reset form to defaults
    resetForm() {
        document.getElementById('eventForm').reset();
        this.selectedWeekdays = [];
        this.selectedParticipants = [];
        this.breakSchedule = [];
        
        // Reset UI state
        document.getElementById('recurrenceOptions').style.display = 'none';
        document.getElementById('weekdaySelector').style.display = 'none';
        document.getElementById('breakSchedule').style.display = 'none';
        document.getElementById('autoCheckoutMinutesGroup').style.display = 'none';
        
        this.updateWeekdayButtons();
        this.populateBreakSchedule();
    }

    // Save event (create or update)
    async saveEvent() {
        try {
            const formData = this.collectFormData();
            
            if (!this.validateFormData(formData)) {
                return;
            }
            
            const action = this.isEditMode ? 'update_event' : 'create_event';
            const response = await this.apiCall(action, formData);
            
            if (response.success) {
                this.showSuccess(response.message || 'Event saved successfully');
                this.closeModal('eventModal');
                this.loadEvents();
                this.loadEventStats();
            } else {
                this.showError(response.error || 'Failed to save event');
            }
        } catch (error) {
            console.error('Error saving event:', error);
            this.showError('Failed to save event');
        }
    }

    // Collect form data
    collectFormData() {
        const formData = {
            name: document.getElementById('eventName').value.trim(),
            description: document.getElementById('eventDescription').value.trim(),
            location: document.getElementById('eventLocation').value.trim(),
            event_type: document.getElementById('eventType').value,
            capacity: document.getElementById('eventCapacity').value,
            start_date: document.getElementById('startDate').value,
            end_date: document.getElementById('endDate').value,
            start_time: document.getElementById('startTime').value,
            end_time: document.getElementById('endTime').value,
            
            // Recurring settings
            recurrence_type: document.getElementById('recurrenceType').value,
            recurrence_interval: document.getElementById('recurrenceInterval').value,
            recurrence_end_date: document.getElementById('recurrenceEndDate').value,
            max_occurrences: document.getElementById('maxOccurrences').value,
            exclude_holidays: document.getElementById('excludeHolidays').checked,
            
            // Break settings
            has_breaks: document.getElementById('hasBreaks').checked,
            
            // Advanced settings
            require_checkin: document.getElementById('requireCheckin').checked,
            allow_manual_checkin: document.getElementById('allowManualCheckin').checked,
            auto_checkout: document.getElementById('autoCheckout').checked,
            auto_checkout_minutes: document.getElementById('autoCheckoutMinutes').value
        };
        
        // Add weekdays for weekly recurrence
        if (formData.recurrence_type === 'weekly' && this.selectedWeekdays.length > 0) {
            formData.recurrence_days = this.selectedWeekdays;
        }
        
        // Add break schedule
        if (formData.has_breaks && this.breakSchedule.length > 0) {
            formData.break_schedule = this.breakSchedule;
        }
        
        // Add event ID for updates
        if (this.isEditMode && this.currentEventId) {
            formData.event_id = this.currentEventId;
        }
        
        return formData;
    }

    // Validate form data
    validateFormData(formData) {
        if (!formData.name) {
            this.showError('Event name is required');
            return false;
        }
        
        if (!formData.start_date) {
            this.showError('Start date is required');
            return false;
        }
        
        if (formData.end_date && formData.start_date > formData.end_date) {
            this.showError('End date cannot be before start date');
            return false;
        }
        
        if (formData.start_time && formData.end_time && formData.start_time >= formData.end_time) {
            this.showError('End time must be after start time');
            return false;
        }
        
        if (formData.recurrence_type === 'weekly' && this.selectedWeekdays.length === 0) {
            this.showError('Please select at least one day for weekly recurrence');
            return false;
        }
        
        return true;
    }

    // Toggle weekday selection
    toggleWeekday(button) {
        const day = parseInt(button.dataset.day);
        const index = this.selectedWeekdays.indexOf(day);
        
        if (index > -1) {
            this.selectedWeekdays.splice(index, 1);
            button.classList.remove('active');
        } else {
            this.selectedWeekdays.push(day);
            button.classList.add('active');
        }
    }

    // Update weekday button states
    updateWeekdayButtons() {
        document.querySelectorAll('.weekday-btn').forEach(btn => {
            const day = parseInt(btn.dataset.day);
            if (this.selectedWeekdays.includes(day)) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }

    // Add break to schedule
    addBreak(breakData = null) {
        const defaultBreak = {
            name: 'Break',
            start: '10:00',
            end: '10:15',
            duration: 15
        };
        
        const breakItem = breakData || defaultBreak;
        this.breakSchedule.push(breakItem);
        this.populateBreakSchedule();
    }

    // Remove break from schedule
    removeBreak(index) {
        this.breakSchedule.splice(index, 1);
        this.populateBreakSchedule();
    }

    // Populate break schedule UI
    populateBreakSchedule() {
        const container = document.getElementById('breaksList');
        
        if (this.breakSchedule.length === 0) {
            container.innerHTML = '<div class="text-muted">No breaks scheduled</div>';
            return;
        }
        
        container.innerHTML = this.breakSchedule.map((breakItem, index) => `
            <div class="pause-item">
                <input type="text" value="${this.escapeHtml(breakItem.name)}" 
                       placeholder="Break name" class="form-control"
                       onchange="eventManager.updateBreak(${index}, 'name', this.value)">
                <input type="time" value="${breakItem.start}" class="form-control"
                       onchange="eventManager.updateBreak(${index}, 'start', this.value)">
                <input type="time" value="${breakItem.end}" class="form-control"
                       onchange="eventManager.updateBreak(${index}, 'end', this.value)">
                <input type="number" value="${breakItem.duration}" min="1" max="480"
                       placeholder="Duration (min)" class="form-control"
                       onchange="eventManager.updateBreak(${index}, 'duration', parseInt(this.value))">
                <button type="button" class="btn btn-danger btn-sm" 
                        onclick="eventManager.removeBreak(${index})">Remove</button>
            </div>
        `).join('');
    }

    // Update break item
    updateBreak(index, field, value) {
        if (this.breakSchedule[index]) {
            this.breakSchedule[index][field] = value;
            
            // Auto-calculate duration for time changes
            if (field === 'start' || field === 'end') {
                const breakItem = this.breakSchedule[index];
                if (breakItem.start && breakItem.end) {
                    const start = new Date(`2000-01-01T${breakItem.start}`);
                    const end = new Date(`2000-01-01T${breakItem.end}`);
                    const duration = Math.round((end - start) / (1000 * 60));
                    if (duration > 0) {
                        breakItem.duration = duration;
                        this.populateBreakSchedule();
                    }
                }
            }
        }
    }

    // Delete event
    async deleteEvent(eventId) {
        if (!confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
            return;
        }
        
        try {
            const response = await this.apiCall('delete_event', { event_id: eventId });
            
            if (response.success) {
                this.showSuccess(response.message || 'Event deleted successfully');
                this.loadEvents();
                this.loadEventStats();
            } else {
                this.showError(response.error || 'Failed to delete event');
            }
        } catch (error) {
            console.error('Error deleting event:', error);
            this.showError('Failed to delete event');
        }
    }

    // Load break templates
    async loadBreakTemplates() {
        try {
            const response = await this.apiCall('load_break_templates');
            
            if (response.success) {
                window.breakTemplates = response.templates;
                this.populateBreakTemplateSelect(response.templates);
            }
        } catch (error) {
            console.error('Error loading break templates:', error);
        }
    }

    // Populate break template select
    populateBreakTemplateSelect(templates) {
        const select = document.getElementById('breakTemplate');
        
        // Clear existing options except first
        while (select.children.length > 1) {
            select.removeChild(select.lastChild);
        }
        
        templates.forEach(template => {
            const option = document.createElement('option');
            option.value = template.name;
            option.textContent = template.name;
            select.appendChild(option);
        });
    }

    // Apply break template
    loadBreakTemplate() {
        const templateSelect = document.getElementById('breakTemplate');
        const selectedTemplate = templateSelect.value;
        
        if (!selectedTemplate || !window.breakTemplates) return;
        
        const template = window.breakTemplates.find(t => t.name === selectedTemplate);
        if (template) {
            this.breakSchedule = [...template.breaks];
            this.populateBreakSchedule();
        }
    }

    // Check for holiday conflicts
    async checkHolidayConflicts() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value || startDate;
        const recurrenceType = document.getElementById('recurrenceType').value;
        
        if (!startDate || recurrenceType === 'one_time') {
            document.getElementById('holidayConflicts').style.display = 'none';
            return;
        }
        
        try {
            const response = await this.apiCall('check_holiday_conflicts', {
                start_date: startDate,
                end_date: endDate
            });
            
            if (response.success && response.has_conflicts) {
                this.displayHolidayConflicts(response.holidays);
            } else {
                document.getElementById('holidayConflicts').style.display = 'none';
            }
        } catch (error) {
            console.error('Error checking holidays:', error);
        }
    }

    // Display holiday conflicts
    displayHolidayConflicts(holidays) {
        const container = document.getElementById('holidayConflicts');
        const list = document.getElementById('holidayList');
        
        if (holidays.length > 0) {
            list.innerHTML = holidays.map(holiday => 
                `<div class="alert alert-warning">
                    <strong>${this.escapeHtml(holiday.name)}</strong> on ${holiday.date} 
                    (${holiday.type === 'national' ? 'National' : 'Regional'})
                    ${holiday.state_codes ? 
                        `<br><small>States: ${JSON.parse(holiday.state_codes).join(', ')}</small>` : ''
                    }
                </div>`
            ).join('');
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
        }
    }

    // Utility methods
    async apiCall(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        
        Object.keys(data).forEach(key => {
            if (data[key] !== null && data[key] !== undefined) {
                if (typeof data[key] === 'object') {
                    formData.append(key, JSON.stringify(data[key]));
                } else {
                    formData.append(key, data[key]);
                }
            }
        });
        
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        
        return await response.json();
    }

    showModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
    }

    closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    showError(message) {
        // Simple alert for now - could be enhanced with better UI
        alert('Error: ' + message);
    }

    showSuccess(message) {
        // Simple alert for now - could be enhanced with better UI
        alert(message);
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Initialize global event manager
const eventManager = new EnhancedEventManager();

// Global functions for HTML onclick handlers
function showCreateEventModal() {
    eventManager.showCreateEventModal();
}

function showGroupManagementModal() {
    alert('User groups feature will be implemented in a future update.');
}

function showHolidayManagementModal() {
    eventManager.showModal('holidayModal');
    loadHolidays();
}

function generateInstancesModal() {
    alert('Instance generation feature available in event edit mode.');
}

function toggleRecurrenceOptions() {
    const type = document.getElementById('recurrenceType').value;
    const options = document.getElementById('recurrenceOptions');
    const weekdaySelector = document.getElementById('weekdaySelector');
    const intervalHelp = document.getElementById('intervalHelpText');
    
    if (type === 'one_time') {
        options.style.display = 'none';
        weekdaySelector.style.display = 'none';
    } else {
        options.style.display = 'block';
        
        // Update help text based on recurrence type
        switch (type) {
            case 'daily':
                intervalHelp.textContent = 'E.g., every 2 days';
                weekdaySelector.style.display = 'none';
                break;
            case 'weekly':
                intervalHelp.textContent = 'E.g., every 2 weeks';
                weekdaySelector.style.display = 'block';
                break;
            case 'monthly':
                intervalHelp.textContent = 'E.g., every 2 months';
                weekdaySelector.style.display = 'none';
                break;
            case 'yearly':
                intervalHelp.textContent = 'E.g., every 2 years';
                weekdaySelector.style.display = 'none';
                break;
        }
        
        // Check for holiday conflicts
        eventManager.checkHolidayConflicts();
    }
}

function toggleBreakSchedule() {
    const hasBreaks = document.getElementById('hasBreaks').checked;
    const schedule = document.getElementById('breakSchedule');
    schedule.style.display = hasBreaks ? 'block' : 'none';
}

function toggleAutoCheckout() {
    const autoCheckout = document.getElementById('autoCheckout').checked;
    const minutesGroup = document.getElementById('autoCheckoutMinutesGroup');
    minutesGroup.style.display = autoCheckout ? 'block' : 'none';
}

function addBreak() {
    eventManager.addBreak();
}

function loadBreakTemplate() {
    eventManager.loadBreakTemplate();
}

function saveEvent() {
    eventManager.saveEvent();
}

function closeModal(modalId) {
    eventManager.closeModal(modalId);
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    eventManager.init();
});
