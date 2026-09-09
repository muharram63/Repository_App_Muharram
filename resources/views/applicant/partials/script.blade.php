{{--   Create Resume---}}
<script>
    // Только фон: белый/лавандовый фон + мягкое живое сияние в тон сайта (голубой/лавандовый/сиреневый)
    const canvas = document.getElementById('wave-bg');
    const ctx = canvas.getContext('2d');
    let w, h;

    function resize(){
        w = canvas.width = window.innerWidth;
        h = canvas.height = window.innerHeight;
    }
    window.addEventListener('resize', resize);
    resize();

    // Оттенки в стиле сайта: светло-голубой, лавандовый, сиреневый, мятный
    const blobs = [
        { color: '167, 199, 255', baseX: 0.05, baseY: 0.15, rx: 0.06, ry: 0.10, speed: 0.10, phase: 0,    size: 0.60 },
        { color: '196, 181, 253', baseX: 0.95, baseY: 0.12, rx: 0.05, ry: 0.12, speed: 0.08, phase: 1.4,  size: 0.65 },
        { color: '180, 210, 255', baseX: 0.08, baseY: 0.85, rx: 0.06, ry: 0.10, speed: 0.09, phase: 2.7,  size: 0.62 },
        { color: '210, 190, 255', baseX: 0.92, baseY: 0.88, rx: 0.05, ry: 0.09, speed: 0.11, phase: 4.1,  size: 0.55 },
        { color: '170, 220, 240', baseX: 0.02, baseY: 0.50, rx: 0.04, ry: 0.12, speed: 0.07, phase: 5.3,  size: 0.55 },
        { color: '190, 195, 255', baseX: 0.98, baseY: 0.55, rx: 0.04, ry: 0.11, speed: 0.10, phase: 3.2,  size: 0.55 }
    ];

    let t = 0;

    function drawBlob(b, shimmer){
        const cx = (b.baseX + Math.sin(t * b.speed + b.phase) * b.rx) * w;
        const cy = (b.baseY + Math.cos(t * b.speed * 0.8 + b.phase) * b.ry) * h;
        const radius = Math.min(w, h) * b.size;

        // лёгкая пульсация яркости — "дыхание" сияния
        const alpha = 0.35 + shimmer * 0.15;

        const grad = ctx.createRadialGradient(cx, cy, 0, cx, cy, radius);
        grad.addColorStop(0,    `rgba(${b.color}, ${alpha})`);
        grad.addColorStop(0.4,  `rgba(${b.color}, ${alpha * 0.6})`);
        grad.addColorStop(0.75, `rgba(${b.color}, ${alpha * 0.2})`);
        grad.addColorStop(1,    `rgba(${b.color}, 0)`);

        ctx.fillStyle = grad;
        ctx.beginPath();
        ctx.arc(cx, cy, radius, 0, Math.PI * 2);
        ctx.fill();
    }

    function render(){
        // базовый фон — светлый, почти белый с лёгким лавандовым оттенком (как на сайте)
        const bgGrad = ctx.createLinearGradient(0, 0, w, h);
        bgGrad.addColorStop(0, '#f7f8fc');
        bgGrad.addColorStop(1, '#eef1fb');
        ctx.fillStyle = bgGrad;
        ctx.fillRect(0, 0, w, h);

        const shimmer = (Math.sin(t * 0.015) + 1) / 2; // плавная пульсация 0..1

        ctx.globalCompositeOperation = 'multiply';
        blobs.forEach(b => drawBlob(b, shimmer));
        ctx.globalCompositeOperation = 'source-over';

        t += 0.004;
        requestAnimationFrame(render);
    }
    render();
</script>
