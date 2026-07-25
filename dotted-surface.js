// ==========================================================================
// Dotted Surface — animated 3D particle wave (adapted from Three.js component)
// ==========================================================================
(function () {
  function init() {
    const container = document.getElementById('dotted-surface');
    if (!container || !window.THREE) return;
    const THREE = window.THREE;

    const SEPARATION = 120;
    const AMOUNTX = 40;
    const AMOUNTY = 50;

    const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';

    const w = () => container.clientWidth || window.innerWidth;
    const h = () => container.clientHeight || 600;

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(60, w() / h(), 1, 10000);
    camera.position.set(0, 355, 1220);

    const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    renderer.setPixelRatio(window.devicePixelRatio);
    renderer.setSize(w(), h());
    renderer.setClearColor(0x000000, 0);
    container.appendChild(renderer.domElement);

    const positions = [];
    const colors = [];
    const geometry = new THREE.BufferGeometry();

    const setColors = () => {
      const dark = isDark();
      const r = dark ? 220 / 255 : 255 / 255;
      const g = dark ? 220 / 255 : 114 / 255;
      const b = dark ? 220 / 255 : 0 / 255;
      const colorAttr = geometry.attributes.color;
      if (!colorAttr) return;
      const arr = colorAttr.array;
      for (let i = 0; i < arr.length; i += 3) {
        arr[i] = r; arr[i + 1] = g; arr[i + 2] = b;
      }
      colorAttr.needsUpdate = true;
    };

    for (let ix = 0; ix < AMOUNTX; ix++) {
      for (let iy = 0; iy < AMOUNTY; iy++) {
        const x = ix * SEPARATION - (AMOUNTX * SEPARATION) / 2;
        const z = iy * SEPARATION - (AMOUNTY * SEPARATION) / 2;
        positions.push(x, 0, z);
        // accent orange
        colors.push(1, 0.447, 0);
      }
    }

    geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
    geometry.setAttribute('color', new THREE.Float32BufferAttribute(colors, 3));

    const material = new THREE.PointsMaterial({
      size: 7,
      vertexColors: true,
      transparent: true,
      opacity: 0.55,
      sizeAttenuation: true,
    });

    const points = new THREE.Points(geometry, material);
    scene.add(points);

    let count = 0;
    let animationId;

    const animate = () => {
      animationId = requestAnimationFrame(animate);
      const arr = geometry.attributes.position.array;
      let i = 0;
      for (let ix = 0; ix < AMOUNTX; ix++) {
        for (let iy = 0; iy < AMOUNTY; iy++) {
          arr[i * 3 + 1] = Math.sin((ix + count) * 0.3) * 50 + Math.sin((iy + count) * 0.5) * 50;
          i++;
        }
      }
      geometry.attributes.position.needsUpdate = true;
      renderer.render(scene, camera);
      count += 0.08;
    };

    const onResize = () => {
      camera.aspect = w() / h();
      camera.updateProjectionMatrix();
      renderer.setSize(w(), h());
    };
    window.addEventListener('resize', onResize);

    // Watch theme
    const themeObs = new MutationObserver(setColors);
    themeObs.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    setColors();

    animate();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
