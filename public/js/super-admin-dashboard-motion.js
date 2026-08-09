(() => {
    const dashboard = document.querySelector('[data-tf-super-admin-dashboard]');
    if (!dashboard) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (reducedMotion.matches) return;

    const reveal = (element, index = 0) => {
        if (!element || element.dataset.tfMotionPlayed === '1') return;
        element.dataset.tfMotionPlayed = '1';
        element.animate([
            { opacity: 0, transform: 'translateY(16px) scale(.985)' },
            { opacity: 1, transform: 'translateY(0) scale(1)' },
        ], {
            delay: Math.min(index, 8) * 55,
            duration: 560,
            easing: 'cubic-bezier(.22, 1, .36, 1)',
            fill: 'both',
        });
    };

    const motionItems = [...dashboard.querySelectorAll('[data-tf-motion-item]')];
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            reveal(entry.target, Number(entry.target.style.getPropertyValue('--tf-motion-order') || 0));
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.08 });
    motionItems.forEach((item) => observer.observe(item));

    const chart = dashboard.querySelector('[data-tf-dashboard-chart]');
    const statuses = dashboard.querySelector('[data-tf-status-distribution]');
    const animateDataVisuals = () => {
        chart?.classList.add('is-motion-ready');
        statuses?.classList.add('is-motion-ready');
    };
    requestAnimationFrame(() => setTimeout(animateDataVisuals, 180));

    const canvas = dashboard.querySelector('[data-tf-dashboard-orbit]');
    if (!canvas || !window.THREE) return;

    const THREE = window.THREE;
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(42, 1, 0.1, 100);
    camera.position.z = 5;

    const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));

    const group = new THREE.Group();
    scene.add(group);
    const positions = [];
    for (let index = 0; index < 80; index += 1) {
        const angle = Math.random() * Math.PI * 2;
        const radius = 0.55 + Math.random() * 1.25;
        positions.push(Math.cos(angle) * radius, Math.sin(angle) * radius, (Math.random() - 0.5) * 0.8);
    }
    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
    group.add(new THREE.Points(geometry, new THREE.PointsMaterial({
        color: 0x2563eb,
        size: 0.035,
        transparent: true,
        opacity: 0.52,
        depthWrite: false,
    })));
    const ringPoints = Array.from({ length: 56 }, (_, index) => {
        const angle = (index / 56) * Math.PI * 2;
        return new THREE.Vector3(Math.cos(angle) * 1.05, Math.sin(angle) * 1.05, 0);
    });
    const ringGeometry = new THREE.BufferGeometry().setFromPoints(ringPoints);
    group.add(new THREE.LineLoop(
        ringGeometry,
        new THREE.LineBasicMaterial({ color: 0x4f46e5, transparent: true, opacity: 0.2 }),
    ));

    const resize = () => {
        const rect = canvas.getBoundingClientRect();
        if (!rect.width || !rect.height) return;
        renderer.setSize(rect.width, rect.height, false);
        camera.aspect = rect.width / rect.height;
        camera.updateProjectionMatrix();
    };
    resize();
    const resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(canvas);

    let frame;
    const render = (time) => {
        group.rotation.z = time * 0.00018;
        group.rotation.x = Math.sin(time * 0.00035) * 0.16;
        renderer.render(scene, camera);
        frame = requestAnimationFrame(render);
    };
    frame = requestAnimationFrame(render);

    window.addEventListener('pagehide', () => {
        cancelAnimationFrame(frame);
        resizeObserver.disconnect();
        geometry.dispose();
        ringGeometry.dispose();
        renderer.dispose();
    }, { once: true });
})();
