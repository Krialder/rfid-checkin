<!-- Breadcrumbs Navigation -->
<nav class="breadcrumbs" aria-label="Breadcrumb navigation">
    <div class="breadcrumbs-container">
        
        <!-- Breadcrumb List -->
        <ol class="breadcrumb-list" itemscope itemtype="https://schema.org/BreadcrumbList">
            
            <!-- Home/Dashboard Link -->
            <li class="breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <a href="/dashboard" class="breadcrumb-link" itemprop="item">
                    <i class="fas fa-home breadcrumb-icon"></i>
                    <span itemprop="name">Dashboard</span>
                </a>
                <meta itemprop="position" content="1">
            </li>
            
            <!-- Dynamic Breadcrumb Items -->
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                <?php $position = $index + 2; ?>
                <li class="breadcrumb-item <?= $index === count($breadcrumbs) - 1 ? 'active' : '' ?>" 
                    itemprop="itemListElement" 
                    itemscope 
                    itemtype="https://schema.org/ListItem">
                    
                    <!-- Breadcrumb Separator -->
                    <span class="breadcrumb-separator" aria-hidden="true">
                        <i class="fas fa-chevron-right"></i>
                    </span>
                    
                    <!-- Current Page (no link) -->
                    <?php if ($index === count($breadcrumbs) - 1): ?>
                        <span class="breadcrumb-current" itemprop="name" aria-current="page">
                            <?php if (!empty($crumb['icon'])): ?>
                                <i class="<?= htmlspecialchars($crumb['icon']) ?> breadcrumb-icon"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($crumb['title']) ?>
                        </span>
                        <meta itemprop="position" content="<?= $position ?>">
                    
                    <!-- Linked Breadcrumb Item -->
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($crumb['url']) ?>" 
                           class="breadcrumb-link" 
                           itemprop="item"
                           title="<?= htmlspecialchars($crumb['title']) ?>">
                            <?php if (!empty($crumb['icon'])): ?>
                                <i class="<?= htmlspecialchars($crumb['icon']) ?> breadcrumb-icon"></i>
                            <?php endif; ?>
                            <span itemprop="name"><?= htmlspecialchars($crumb['title']) ?></span>
                        </a>
                        <meta itemprop="position" content="<?= $position ?>">
                    <?php endif; ?>
                    
                </li>
            <?php endforeach; ?>
            
        </ol>
        
        <!-- Page Actions (if provided) -->
        <?php if (!empty($page_actions)): ?>
            <div class="breadcrumb-actions">
                <?php foreach ($page_actions as $action): ?>
                    <a href="<?= htmlspecialchars($action['url']) ?>" 
                       class="btn btn-<?= htmlspecialchars($action['type'] ?? 'primary') ?> btn-sm breadcrumb-action"
                       <?php if (!empty($action['target'])): ?>target="<?= htmlspecialchars($action['target']) ?>"<?php endif; ?>
                       title="<?= htmlspecialchars($action['title']) ?>">
                        <?php if (!empty($action['icon'])): ?>
                            <i class="<?= htmlspecialchars($action['icon']) ?>"></i>
                        <?php endif; ?>
                        <span class="action-text"><?= htmlspecialchars($action['title']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
    </div>
</nav>

<!-- Breadcrumbs JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const breadcrumbs = document.querySelector('.breadcrumbs');
    
    if (breadcrumbs) {
        // Handle breadcrumb truncation on small screens
        handleBreadcrumbTruncation();
        
        // Re-check on window resize
        window.addEventListener('resize', handleBreadcrumbTruncation);
        
        // Add keyboard navigation
        addKeyboardNavigation();
    }
    
    function handleBreadcrumbTruncation() {
        const breadcrumbList = document.querySelector('.breadcrumb-list');
        const breadcrumbItems = document.querySelectorAll('.breadcrumb-item');
        
        if (!breadcrumbList || breadcrumbItems.length <= 3) return;
        
        // Reset all items to visible
        breadcrumbItems.forEach(item => {
            item.classList.remove('breadcrumb-hidden');
        });
        
        // Check if truncation is needed on mobile
        if (window.innerWidth < 768 && breadcrumbItems.length > 3) {
            // Hide middle items, keep first, second-to-last, and last
            breadcrumbItems.forEach((item, index) => {
                if (index > 0 && index < breadcrumbItems.length - 2) {
                    item.classList.add('breadcrumb-hidden');
                }
            });
            
            // Add ellipsis indicator if not already present
            if (!document.querySelector('.breadcrumb-ellipsis')) {
                const ellipsis = document.createElement('li');
                ellipsis.className = 'breadcrumb-item breadcrumb-ellipsis';
                ellipsis.innerHTML = `
                    <span class="breadcrumb-separator" aria-hidden="true">
                        <i class="fas fa-chevron-right"></i>
                    </span>
                    <span class="breadcrumb-dots" title="More breadcrumbs">...</span>
                `;
                
                const secondToLast = breadcrumbItems[breadcrumbItems.length - 2];
                breadcrumbList.insertBefore(ellipsis, secondToLast);
            }
        } else {
            // Remove ellipsis if present
            const ellipsis = document.querySelector('.breadcrumb-ellipsis');
            if (ellipsis) {
                ellipsis.remove();
            }
        }
    }
    
    function addKeyboardNavigation() {
        const breadcrumbLinks = document.querySelectorAll('.breadcrumb-link');
        
        breadcrumbLinks.forEach((link, index) => {
            link.addEventListener('keydown', function(e) {
                let targetIndex = -1;
                
                switch(e.key) {
                    case 'ArrowLeft':
                        targetIndex = index - 1;
                        break;
                    case 'ArrowRight':
                        targetIndex = index + 1;
                        break;
                    case 'Home':
                        targetIndex = 0;
                        break;
                    case 'End':
                        targetIndex = breadcrumbLinks.length - 1;
                        break;
                }
                
                if (targetIndex >= 0 && targetIndex < breadcrumbLinks.length) {
                    e.preventDefault();
                    breadcrumbLinks[targetIndex].focus();
                }
            });
        });
    }
});

// Function to update breadcrumbs dynamically
window.updateBreadcrumbs = function(newBreadcrumbs) {
    const breadcrumbList = document.querySelector('.breadcrumb-list');
    if (!breadcrumbList) return;
    
    // Clear existing breadcrumbs (except home)
    const existingItems = breadcrumbList.querySelectorAll('.breadcrumb-item:not(:first-child)');
    existingItems.forEach(item => item.remove());
    
    // Add new breadcrumbs
    newBreadcrumbs.forEach((crumb, index) => {
        const position = index + 2;
        const isLast = index === newBreadcrumbs.length - 1;
        
        const li = document.createElement('li');
        li.className = `breadcrumb-item ${isLast ? 'active' : ''}`;
        li.setAttribute('itemprop', 'itemListElement');
        li.setAttribute('itemscope', '');
        li.setAttribute('itemtype', 'https://schema.org/ListItem');
        
        let content = `
            <span class="breadcrumb-separator" aria-hidden="true">
                <i class="fas fa-chevron-right"></i>
            </span>
        `;
        
        if (isLast) {
            content += `
                <span class="breadcrumb-current" itemprop="name" aria-current="page">
                    ${crumb.icon ? `<i class="${escapeHtml(crumb.icon)} breadcrumb-icon"></i>` : ''}
                    ${escapeHtml(crumb.title)}
                </span>
            `;
        } else {
            content += `
                <a href="${escapeHtml(crumb.url)}" 
                   class="breadcrumb-link" 
                   itemprop="item"
                   title="${escapeHtml(crumb.title)}">
                    ${crumb.icon ? `<i class="${escapeHtml(crumb.icon)} breadcrumb-icon"></i>` : ''}
                    <span itemprop="name">${escapeHtml(crumb.title)}</span>
                </a>
            `;
        }
        
        content += `<meta itemprop="position" content="${position}">`;
        
        li.innerHTML = content;
        breadcrumbList.appendChild(li);
    });
    
    // Re-apply truncation logic
    handleBreadcrumbTruncation();
};

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<!-- Breadcrumbs CSS (inline for immediate loading) -->
<style>
/* Breadcrumbs Container */
.breadcrumbs {
    background: rgba(255, 255, 255, 0.95);
    border-bottom: 1px solid #e2e8f0;
    padding: 0.75rem 0;
    margin-bottom: 1rem;
    backdrop-filter: blur(10px);
    position: sticky;
    top: 0;
    z-index: 100;
}

.breadcrumbs-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

/* Breadcrumb List */
.breadcrumb-list {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    margin: 0;
    padding: 0;
    list-style: none;
    gap: 0.25rem;
}

.breadcrumb-item {
    display: flex;
    align-items: center;
    font-size: 0.875rem;
    color: #64748b;
}

.breadcrumb-item.breadcrumb-hidden {
    display: none;
}

/* Breadcrumb Links */
.breadcrumb-link {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    color: #64748b;
    text-decoration: none;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
    max-width: 200px;
}

.breadcrumb-link:hover {
    color: #3b82f6;
    background: rgba(59, 130, 246, 0.1);
    text-decoration: none;
}

.breadcrumb-link:focus {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

/* Current Page */
.breadcrumb-current {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    color: #1e293b;
    font-weight: 600;
    padding: 0.25rem 0.5rem;
    max-width: 200px;
}

/* Breadcrumb Icons */
.breadcrumb-icon {
    flex-shrink: 0;
    font-size: 0.875rem;
}

/* Separators */
.breadcrumb-separator {
    margin: 0 0.25rem;
    color: #cbd5e1;
    font-size: 0.75rem;
}

/* Ellipsis */
.breadcrumb-dots {
    color: #94a3b8;
    font-weight: bold;
    padding: 0.25rem 0.5rem;
}

/* Page Actions */
.breadcrumb-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.breadcrumb-action {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    white-space: nowrap;
}

/* Responsive Design */
@media (max-width: 768px) {
    .breadcrumbs-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .breadcrumb-link,
    .breadcrumb-current {
        max-width: 150px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .breadcrumb-actions {
        width: 100%;
        justify-content: flex-end;
    }
    
    .action-text {
        display: none;
    }
}

@media (max-width: 480px) {
    .breadcrumbs {
        padding: 0.5rem 0;
    }
    
    .breadcrumb-item {
        font-size: 0.8125rem;
    }
    
    .breadcrumb-link,
    .breadcrumb-current {
        max-width: 120px;
        padding: 0.1875rem 0.375rem;
    }
}

/* Dark Theme Support */
[data-theme="dark"] .breadcrumbs {
    background: rgba(45, 55, 72, 0.95);
    border-bottom-color: #4a5568;
}

[data-theme="dark"] .breadcrumb-item {
    color: #a0aec0;
}

[data-theme="dark"] .breadcrumb-link {
    color: #a0aec0;
}

[data-theme="dark"] .breadcrumb-link:hover {
    color: #63b3ed;
    background: rgba(99, 179, 237, 0.1);
}

[data-theme="dark"] .breadcrumb-current {
    color: #e2e8f0;
}

[data-theme="dark"] .breadcrumb-separator {
    color: #718096;
}

/* High Contrast Mode */
@media (prefers-contrast: high) {
    .breadcrumbs {
        border-bottom-width: 2px;
    }
    
    .breadcrumb-link:focus {
        outline-width: 3px;
    }
}

/* Reduced Motion */
@media (prefers-reduced-motion: reduce) {
    .breadcrumb-link {
        transition: none;
    }
}

/* Print Styles */
@media print {
    .breadcrumbs {
        background: none;
        border-bottom: 1px solid #000;
        position: static;
    }
    
    .breadcrumb-actions {
        display: none;
    }
}
</style>
