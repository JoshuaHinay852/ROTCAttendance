<?php
// enhanced_page_transition.php
?>
<!-- Page Transition Overlay -->
<div id="pageTransition" class="fixed inset-0 z-[9999] pointer-events-none">
    <div class="absolute inset-0 bg-blue-600 transition-all duration-500 ease-in-out opacity-0" id="transitionBg"></div>
</div>

<script>
// Page transition configuration
const TRANSITION_TYPES = {
    CIRCLE: 'circle',
    FADE: 'fade',
    SLIDE: 'slide',
    WAVE: 'wave'
};

// Set your preferred transition type
const PREFERRED_TRANSITION = TRANSITION_TYPES.CIRCLE;

function startPageTransition(href, type = PREFERRED_TRANSITION) {
    const transition = document.getElementById('transitionBg');
    
    // Show the transition overlay
    transition.classList.remove('opacity-0');
    transition.classList.add('opacity-100');
    
    // Apply different animations based on type
    switch(type) {
        case TRANSITION_TYPES.CIRCLE:
            transition.style.clipPath = 'circle(0% at 50% 50%)';
            transition.style.transition = 'clip-path 0.7s cubic-bezier(0.4, 0, 0.2, 1)';
            
            setTimeout(() => {
                transition.style.clipPath = 'circle(150% at 50% 50%)';
                setTimeout(() => window.location.href = href, 350);
            }, 50);
            break;
            
        case TRANSITION_TYPES.FADE:
            transition.style.transition = 'opacity 0.4s ease';
            transition.style.opacity = '0';
            
            setTimeout(() => {
                transition.style.opacity = '1';
                setTimeout(() => window.location.href = href, 400);
            }, 50);
            break;
            
        case TRANSITION_TYPES.SLIDE:
            transition.style.transform = 'translateX(-100%)';
            transition.style.transition = 'transform 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
            
            setTimeout(() => {
                transition.style.transform = 'translateX(0%)';
                setTimeout(() => window.location.href = href, 300);
            }, 50);
            break;
            
        case TRANSITION_TYPES.WAVE:
            transition.style.clipPath = 'polygon(0% 0%, 100% 0%, 100% 0%, 0% 0%)';
            transition.style.transition = 'clip-path 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
            
            setTimeout(() => {
                transition.style.clipPath = 'polygon(0% 0%, 100% 0%, 100% 100%, 0% 100%)';
                setTimeout(() => window.location.href = href, 400);
            }, 50);
            break;
    }
}

// Handle all internal link clicks
document.addEventListener('DOMContentLoaded', function() {
    const links = document.querySelectorAll('a[href^="/"], a[href^="./"], a[href^="../"], a[href^="http"]:not([target="_blank"])');
    
    links.forEach(link => {
        // Skip if it's a hash link, download link, or has a special attribute
        if (link.href.includes('#') || 
            link.hasAttribute('download') || 
            link.hasAttribute('data-no-transition') ||
            link.getAttribute('href') === '#' ||
            link.getAttribute('href') === 'javascript:void(0)') {
            return;
        }
        
        link.addEventListener('click', function(e) {
            // Don't intercept if ctrl/command/shift key is pressed
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) {
                return;
            }
            
            // Don't intercept if target is _blank
            if (this.target === '_blank') {
                return;
            }
            
            e.preventDefault();
            const href = this.href;
            
            // You can assign different transitions based on link classes
            let transitionType = PREFERRED_TRANSITION;
            
            if (this.classList.contains('fade-transition')) {
                transitionType = TRANSITION_TYPES.FADE;
            } else if (this.classList.contains('slide-transition')) {
                transitionType = TRANSITION_TYPES.SLIDE;
            } else if (this.classList.contains('wave-transition')) {
                transitionType = TRANSITION_TYPES.WAVE;
            }
            
            startPageTransition(href, transitionType);
        });
    });
    
    // When page loads, animate out
    const transition = document.getElementById('transitionBg');
    transition.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
    
    // Reverse the animation based on type
    setTimeout(() => {
        switch(PREFERRED_TRANSITION) {
            case TRANSITION_TYPES.CIRCLE:
                transition.style.clipPath = 'circle(0% at 50% 50%)';
                break;
            case TRANSITION_TYPES.FADE:
                transition.style.opacity = '0';
                break;
            case TRANSITION_TYPES.SLIDE:
                transition.style.transform = 'translateX(100%)';
                break;
            case TRANSITION_TYPES.WAVE:
                transition.style.clipPath = 'polygon(0% 100%, 100% 100%, 100% 100%, 0% 100%)';
                break;
        }
        
        setTimeout(() => {
            transition.classList.remove('opacity-100');
            transition.classList.add('opacity-0');
        }, 600);
    }, 100);
});

// Add smooth scroll for anchor links
document.addEventListener('click', function(e) {
    if (e.target.tagName === 'A' && e.target.getAttribute('href')?.startsWith('#')) {
        e.preventDefault();
        const targetId = e.target.getAttribute('href').substring(1);
        const targetElement = document.getElementById(targetId);
        
        if (targetElement) {
            window.scrollTo({
                top: targetElement.offsetTop - 80,
                behavior: 'smooth'
            });
        }
    }
});
</script>

<style>
#pageTransition {
    z-index: 9999;
}

#transitionBg {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    will-change: clip-path, transform, opacity;
}

/* Add subtle page load animation for content */
.main-content {
    animation: pageLoad 0.5s ease-out;
}

@keyframes pageLoad {
    from {
        opacity: 0.8;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Smooth scrolling for the whole page */
html {
    scroll-behavior: smooth;
}
</style>