(function() {
  // Version tag to bust caches.
  console.log('[fullscreen v3] script loaded on', window.location.href);

  const getLabel = () => document.querySelector('label.me-2.mb-0.form-check-label');
  let courseClicked = false;
  let scormClicked = false;

  const adjustScormPlayer = () => {
    const onPlayer = window.location.pathname.endsWith('scorm/player.php');
    console.log('[fullscreen v3] adjustScormPlayer check', { onPlayer, label: getLabel() });
    if (!onPlayer) {
      return;
    }

    const exitBtn = document.querySelector('a.btn.btn-secondary[title="Exit activity"]');
    console.log('[fullscreen v3] exit button found?', !!exitBtn);
    if (!exitBtn) {
      return;
    }

    exitBtn.style.position = 'fixed';
    exitBtn.style.bottom = '20px';
    exitBtn.style.right = '80px';
    exitBtn.style.zIndex = '1000';
    exitBtn.style.margin = '0';

    const pagination = document.querySelector('.lia-pagination');
    if (pagination) {
      pagination.remove();
    }

    const elementsToRemove = [
      document.querySelector('div.d-flex.align-items-center'),
      document.querySelector('div.d-flex.flex-wrap'),
      document.querySelector('ul.more-nav.nav-tabs'),
      document.querySelector('div#scormtop'),
      document.querySelector('div.secondary-navigation.d-print-none'),
    ];

    elementsToRemove.forEach((el) => {
      if (el) {
        el.remove();
        console.log('[fullscreen v3] removed element', el);
      }
    });

    const toggle = document.getElementById('scorm_toc_toggle_btn');
    console.log('[fullscreen v3] toc toggle found?', !!toggle);
    if (toggle) {
      setTimeout(() => toggle.click(), 300);
    }
  };

  const tryClickActivity = () => {
    if (courseClicked) {
      return true;
    }
    const anchor = document.querySelector('div.activityname a.aalink.stretched-link') ||
      document.querySelector('a.aalink.stretched-link');
    console.log('[fullscreen v3] activity link found?', !!anchor, anchor);
    if (anchor) {
      courseClicked = true;
      anchor.click();
      return true;
    }
    return false;
  };

  const autoOpenFirstActivity = () => {
    const onCourse = window.location.href.includes('course/view.php');
    console.log('[fullscreen v3] autoOpenFirstActivity', { onCourse, label: getLabel() });
    if (!onCourse) {
      return;
    }
    // Try immediately, then a few more times while the page settles.
    let attempts = 0;
    const interval = setInterval(() => {
      attempts += 1;
      const done = tryClickActivity();
      if (done || attempts >= 10) {
        clearInterval(interval);
      }
    }, 300);
  };

  const tryClickScormEnter = () => {
    if (scormClicked) {
      return true;
    }
    const checkbox = document.getElementById('a');
    console.log('[fullscreen v3] checkbox found?', !!checkbox);
    if (checkbox) {
      checkbox.checked = true;
      checkbox.dispatchEvent(new Event('change'));
    }

    const launchBtn = document.getElementById('n');
    console.log('[fullscreen v3] launch button found?', !!launchBtn, launchBtn);
    if (launchBtn) {
      scormClicked = true;
      launchBtn.click();
      return true;
    }
    return false;
  };

  const autoLaunchScorm = () => {
    const onScormView = window.location.href.includes('scorm/view.php');
    console.log('[fullscreen v3] autoLaunchScorm', { onScormView, label: getLabel() });
    if (!onScormView) {
      return;
    }
    let attempts = 0;
    const interval = setInterval(() => {
      attempts += 1;
      const done = tryClickScormEnter();
      if (done || attempts >= 10) {
        clearInterval(interval);
      }
    }, 300);
  };

  const runScripts = () => {
    console.log('[fullscreen v3] running scripts');
    adjustScormPlayer();
    autoOpenFirstActivity();
    autoLaunchScorm();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      runScripts();
      setTimeout(runScripts, 500);
    });
  } else {
    runScripts();
    setTimeout(runScripts, 500);
  }
})();
