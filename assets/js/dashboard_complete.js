/*
 * Dashboard JavaScript
 * Handles dynamic content loading and interactions
 */

class Dashboard {
    constructor() {
        this.init();
    }
    
    init() {
        this.loadDashboardData();
        this.setupEventListeners();
        this.setupModal();
    }
    
    async loadDashboardData() {
        try {
            const response = await fetch('api/dashboard.php');
            const data = await response.json();
            
            if (data.error) {
                this.showError(data.error);
                return;
            }
            
            this.updateStats(data.stats || {});
            this.updateRecentCheckins(data.recent_checkins || []);
            this.updateUpcomingEvents(data.upcoming_events || []);
            this.updateAvailableEvents(data.available_events || []);
            
        } catch (error) {
            console.error('Failed to load dashboard data:', error);
            this.showError('Failed to load dashboard data');
        }
    }
    
    updateStats(stats) {
        const statsContainer = document.querySelector('.stats-grid');
        if (!statsContainer) return;
        
        statsContainer.innerHTML = `
            <div class="stat-item">
                <div class="stat-number">${stats.total_checkins || 0}</div>
                <div class="stat-label">Total Check-ins</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">${stats.events_attended || 0}</div>
                <div class="stat-label">Events Attended</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">${stats.this_week || 0}</div>
                <div class="stat-label">This Week</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">${stats.this_month || 0}</div>
                <div class="stat-label">This Month</div>
            </div>
        `;
    }
    
    updateRecentCheckins(checkins) {
        const container = document.querySelector('#recent-checkins');
        if (!container) return;
        
        if (checkins.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>No recent check-ins</h3>
                    <p>Your recent check-ins will appear here</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = checkins.map(checkin => `
            <div class="checkin-item">
                <div class="checkin-info">
                    <h4>${this.escapeHtml(checkin.event_name)}</h4>
                    <div class="checkin-details">
                        <span class="checkin-time">
                            📅 ${this.formatDateTime(checkin.checkin_time)}
                        </span>
                        ${checkin.location ? `<span>📍 ${this.escapeHtml(checkin.location)}</span>` : ''}
                    </div>
                </div>
                <span class="status-badge status-${checkin.status}">
                    ${checkin.status === 'checked-in' ? '✅' : '⏱️'} ${checkin.status}
                </span>
            </div>
        `).join('');
    }
    
    updateUpcomingEvents(events) {
        const container = document.querySelector('#upcoming-events');
        if (!container) return;
        
        if (events.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">📅</div>
                    <h3>No upcoming events</h3>
                    <p>Check back later for new events</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = events.map(event => `
            <div class="event-item">
                <div class="event-header">
                    <div class="event-name">${this.escapeHtml(event.event_name)}</div>
                    <div class="event-date">${this.formatDate(event.start_time)}</div>
                </div>
                <div class="event-details">
                    ${event.location ? `<div class="event-location">📍 ${this.escapeHtml(event.location)}</div>` : ''}
                    <div>⏰ ${this.formatTime(event.start_time)} - ${this.formatTime(event.end_time)}</div>
                    ${event.description ? `<div>${this.escapeHtml(event.description)}</div>` : ''}
                </div>
            </div>
        `).join('');
    }
    
    updateAvailableEvents(events) {
        const select = document.querySelector('#eventSelect');
        if (!select) return;
        
        select.innerHTML = '<option value="">Select an event...</option>' +
            events.map(event => `
                <option value="${event.event_id}">
                    ${this.escapeHtml(event.event_name)} - ${this.formatDateTime(event.start_time)}
                </option>
            `).join('');
    }
    
    setupEventListeners() {
        // Manual check-in form
        const checkInForm = document.getElementById('manualCheckInForm');
        if (checkInForm) {
            checkInForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(checkInForm);
                await this.handleManualCheckIn(formData);
            });
        }
        
        // Modal close buttons
        document.querySelectorAll('.close, .modal-close').forEach(button => {
            button.addEventListener('click', (e) => {
                const modal = e.target.closest('.modal');
                if (modal) this.closeModal(modal.id);
            });
        });
        
        // Close modal on background click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) this.closeModal(modal.id);
            });
        });
    }
    
    setupModal() {
        // Set up modal content if needed
        const modalContent = document.querySelector('#checkInModal .modal-content');
        if (modalContent && !modalContent.innerHTML.includes('form')) {
            modalContent.innerHTML = `
                <span class="close">&times;</span>
                <h3>📋 Manual Check-in</h3>
                <form id="manualCheckInForm">
                    <div class="form-group">
                        <label for="eventSelect">Select Event:</label>
                        <select id="eventSelect" name="event_id" required>
                            <option value="">Loading events...</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Check In</button>
                        <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                    </div>
                </form>
            `;
        }
    }
    
    async handleManualCheckIn(formData) {
        try {
            const response = await fetch('api/manual_checkin.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess(result.message);
                this.closeModal('checkInModal');
                // Refresh dashboard data
                this.loadDashboardData();
            } else {
                this.showError(result.error || 'Check-in failed');
            }
        } catch (error) {
            this.showError('Network error occurred');
            console.error('Manual check-in error:', error);
        }
    }
    
    showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
        }
    }
    
    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
        }
    }
    
    showError(message) {
        this.showNotification(message, 'error');
    }
    
    showSuccess(message) {
        this.showNotification(message, 'success');
    }
    
    showNotification(message, type = 'info') {
        // Remove existing notifications
        document.querySelectorAll('.notification').forEach(n => n.remove());
        
        // Create notification
        const notification = document.createElement('div');
        notification.className = `notification alert alert-${type}`;
        notification.innerHTML = `
            <span>${this.escapeHtml(message)}</span>
            <button class="notification-close" onclick="this.parentElement.remove()">&times;</button>
        `;
        
        // Add to page
        const container = document.querySelector('.main-content') || document.body;
        container.insertBefore(notification, container.firstChild);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
        
        // Add notification styles if not present
        if (!document.querySelector('#notification-styles')) {
            const style = document.createElement('style');
            style.id = 'notification-styles';
            style.textContent = `
                .notification {
                    position: fixed;
                    top: 80px;
                    right: 20px;
                    z-index: 9999;
                    min-width: 300px;
                    max-width: 500px;
                    padding: 15px 20px;
                    border-radius: 6px;
                    box-shadow: var(--shadow-lg);
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    animation: slideInRight 0.3s ease-out;
                }
                
                .notification-close {
                    background: none;
                    border: none;
                    color: inherit;
                    font-size: 1.2rem;
                    cursor: pointer;
                    margin-left: 10px;
                    padding: 0;
                    width: 24px;
                    height: 24px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 50%;
                    opacity: 0.7;
                }
                
                .notification-close:hover {
                    opacity: 1;
                    background: rgba(0,0,0,0.1);
                }
                
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
            `;
            document.head.appendChild(style);
        }
    }
    
    // Utility functions
    escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    
    formatDateTime(dateString) {
        return new Date(dateString).toLocaleString();
    }
    
    formatDate(dateString) {
        return new Date(dateString).toLocaleDateString();
    }
    
    formatTime(dateString) {
        return new Date(dateString).toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
    }
}

// Global function to show check-in modal
window.showCheckInModal = function() {
    const dashboard = window.dashboardInstance;
    if (dashboard) {
        dashboard.showModal('checkInModal');
    }
};

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.dashboardInstance = new Dashboard();
});
