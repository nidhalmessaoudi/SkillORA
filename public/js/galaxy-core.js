/**
 * GalaxyCore — Immersive 3D Spiral Galaxy Renderer
 * Dual-layer star system: crisp bright core + large soft halo.
 * No fog (fog causes blurriness). Rich vivid colours.
 */

import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';

// ─── Helpers ─────────────────────────────────────────────────────────────────

function lerp(a, b, t) { return a + (b - a) * t; }
function randRange(min, max) { return Math.random() * (max - min) + min; }

/** Crisp star texture: hard bright white center, thin soft halo */
function makeStarTexture(size = 128) {
    const c   = document.createElement('canvas');
    c.width   = c.height = size;
    const ctx = c.getContext('2d');
    const h   = size / 2;
    const g   = ctx.createRadialGradient(h, h, 0, h, h, h);
    g.addColorStop(0.00, 'rgba(255,255,255,1.00)');
    g.addColorStop(0.04, 'rgba(255,255,255,0.98)');
    g.addColorStop(0.12, 'rgba(255,255,255,0.70)');
    g.addColorStop(0.28, 'rgba(255,255,255,0.20)');
    g.addColorStop(0.55, 'rgba(255,255,255,0.05)');
    g.addColorStop(1.00, 'rgba(255,255,255,0.00)');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, size, size);
    return new THREE.CanvasTexture(c);
}

/** Large soft halo for bright foreground stars */
function makeHaloTexture(size = 256, hexColor) {
    const c   = document.createElement('canvas');
    c.width   = c.height = size;
    const ctx = c.getContext('2d');
    const h   = size / 2;
    const col = new THREE.Color(hexColor);
    const rgb = `${Math.round(col.r*255)},${Math.round(col.g*255)},${Math.round(col.b*255)}`;
    const g   = ctx.createRadialGradient(h, h, 0, h, h, h);
    g.addColorStop(0.00, `rgba(${rgb},0.95)`);
    g.addColorStop(0.10, `rgba(${rgb},0.80)`);
    g.addColorStop(0.30, `rgba(${rgb},0.35)`);
    g.addColorStop(0.60, `rgba(${rgb},0.08)`);
    g.addColorStop(1.00, `rgba(${rgb},0.00)`);
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, size, size);
    return new THREE.CanvasTexture(c);
}

/** Organic nebula cloud */
function makeNebulaTexture(size = 256, hex) {
    const c   = document.createElement('canvas');
    c.width   = c.height = size;
    const ctx = c.getContext('2d');
    const col = new THREE.Color(hex);
    const rgb = `${Math.round(col.r*255)},${Math.round(col.g*255)},${Math.round(col.b*255)}`;
    const h   = size / 2;
    for (let i = 0; i < 5; i++) {
        const ox = randRange(h * 0.2, h * 0.8);
        const oy = randRange(h * 0.2, h * 0.8);
        const r  = randRange(h * 0.35, h);
        const g  = ctx.createRadialGradient(ox, oy, 0, ox, oy, r);
        g.addColorStop(0.0, `rgba(${rgb},0.28)`);
        g.addColorStop(0.4, `rgba(${rgb},0.14)`);
        g.addColorStop(1.0, `rgba(${rgb},0.00)`);
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, size, size);
    }
    return new THREE.CanvasTexture(c);
}

// ─── Colour palettes ──────────────────────────────────────────────────────────

const CORE_COLORS   = [0xfff8e7, 0xfff4cc, 0xffe8a0, 0xffd97d, 0xffcc66, 0xffeedd];
const ARM_INNER     = [0xfff5cc, 0xffd966, 0xffe680, 0xfff0aa];
const ARM_MID       = [0xaad4ff, 0x80c4ff, 0x60a5fa, 0x93c5fd, 0xbae6fd, 0xc7d2fe];
const ARM_OUTER     = [0xa5b4fc, 0x818cf8, 0x7c3aed, 0xc084fc, 0xe879f9];
const HALO_COLORS   = [0xdde8ff, 0xeef0ff, 0xffd6f0, 0xe0ccff, 0xffffff, 0xccddff];
const BRIGHT_STARS  = [0xffffff, 0xfff8e0, 0xe0f0ff, 0xffe0f0, 0xf0e0ff];
const NEBULA_COLORS = ['#7c3aed', '#6d28d9', '#2563eb', '#0891b2', '#be185d', '#1d4ed8', '#7e22ce', '#0e7490'];

// ─── Main Class ───────────────────────────────────────────────────────────────

export class GalaxyCore {
    constructor(container, options = {}) {
        this.el = typeof container === 'string'
            ? document.querySelector(container)
            : container;
        if (!this.el) return;

        this.opts = Object.assign({
            starCount:       120000,
            arms:            5,
            armTightness:    0.42,
            coreRadius:      2.0,
            galaxyRadius:    15.0,
            coreStarFrac:    0.20,
            nebulaCount:     40,
            brightStarCount: 2800,    // extra bright foreground stars
            enableControls:  true,
            autoRotate:      true,
            autoRotateSpeed: 0.22,
            fov:             58,
            background:      0x000008,
            onReady:         null,
        }, options);

        this._twinkleData = [];
        this._time        = 0;
        this._disposed    = false;
        this._init();
    }

    // ── Setup ─────────────────────────────────────────────────────────────────

    _init() {
        const W = this.el.clientWidth  || window.innerWidth;
        const H = this.el.clientHeight || window.innerHeight;

        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(this.opts.background);
        // NO FOG — fog is the main cause of blurriness

        this.camera = new THREE.PerspectiveCamera(this.opts.fov, W / H, 0.1, 600);
        this.camera.position.set(0, 7, 20);
        this.camera.lookAt(0, 0, 0);

        this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false });
        this.renderer.setSize(W, H);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.renderer.outputColorSpace = THREE.SRGBColorSpace;
        this.el.appendChild(this.renderer.domElement);

        if (this.opts.enableControls) {
            this.controls = new OrbitControls(this.camera, this.renderer.domElement);
            this.controls.enableDamping   = true;
            this.controls.dampingFactor   = 0.04;
            this.controls.autoRotate      = this.opts.autoRotate;
            this.controls.autoRotateSpeed = this.opts.autoRotateSpeed;
            this.controls.enablePan       = false;
            this.controls.minDistance     = 3;
            this.controls.maxDistance     = 55;
            this.controls.maxPolarAngle   = Math.PI * 0.72;
        }

        this._buildGalaxy();
        this._buildBrightStars();
        this._buildNebulae();
        this._buildCoreGlow();
        this._buildDustLane();

        this._onResize = this._handleResize.bind(this);
        window.addEventListener('resize', this._onResize);

        this._clock = new THREE.Clock();
        this._animate();

        if (this.opts.onReady) this.opts.onReady(this);
    }

    // ── Galaxy point cloud ────────────────────────────────────────────────────

    _buildGalaxy() {
        const { starCount: N, arms, armTightness: tightB, galaxyRadius: R,
                coreRadius: coreR, coreStarFrac: coreFr } = this.opts;

        const positions = new Float32Array(N * 3);
        const colors    = new Float32Array(N * 3);
        const sizes     = new Float32Array(N);
        const coreCount = Math.floor(N * coreFr);
        const armCount  = N - coreCount;
        const tmpColor  = new THREE.Color();

        // ── Core stars ────────────────────────────────────────────────────────
        for (let i = 0; i < coreCount; i++) {
            const r     = Math.pow(Math.random(), 1.8) * coreR * 1.7;
            const theta = Math.random() * Math.PI * 2;
            const phi   = (Math.random() - 0.5) * Math.PI * 0.30;

            positions[i*3]     = r * Math.cos(theta) * Math.cos(phi);
            positions[i*3 + 1] = r * Math.sin(phi) * 0.5;
            positions[i*3 + 2] = r * Math.sin(theta) * Math.cos(phi);

            const warm = 1 - (r / (coreR * 1.7));
            tmpColor.setHex(CORE_COLORS[Math.floor(Math.random() * CORE_COLORS.length)]);
            tmpColor.multiplyScalar(lerp(0.7, 1.15, warm));
            colors[i*3]     = tmpColor.r;
            colors[i*3 + 1] = tmpColor.g;
            colors[i*3 + 2] = tmpColor.b;
            sizes[i] = randRange(3.0, 8.5) * (0.45 + warm * 0.55);
        }

        // ── Spiral arm stars ──────────────────────────────────────────────────
        for (let i = 0; i < armCount; i++) {
            const idx       = coreCount + i;
            const t         = Math.pow(Math.random(), 0.52);
            const r         = coreR * 0.5 + t * (R - coreR * 0.5);
            const armIndex  = Math.floor(Math.random() * arms);
            const armOffset = (armIndex / arms) * Math.PI * 2;
            const theta     = (Math.log(r / 0.45)) / tightB + armOffset;
            const scatter   = randRange(-1, 1) * Math.pow(t, 0.65) * 2.2;
            const yScatter  = randRange(-1, 1) * 0.20 * (1 - t * 0.65);

            positions[idx*3]     = r * Math.cos(theta + scatter * 0.13) + scatter * 0.28;
            positions[idx*3 + 1] = yScatter;
            positions[idx*3 + 2] = r * Math.sin(theta + scatter * 0.13) + scatter * 0.28;

            // Color bands: warm inner → vivid blue mid → purple outer
            let col;
            if      (t < 0.28) col = ARM_INNER[Math.floor(Math.random() * ARM_INNER.length)];
            else if (t < 0.65) col = ARM_MID[Math.floor(Math.random() * ARM_MID.length)];
            else if (t < 0.85) col = ARM_OUTER[Math.floor(Math.random() * ARM_OUTER.length)];
            else               col = HALO_COLORS[Math.floor(Math.random() * HALO_COLORS.length)];

            tmpColor.setHex(col);
            colors[idx*3]     = tmpColor.r;
            colors[idx*3 + 1] = tmpColor.g;
            colors[idx*3 + 2] = tmpColor.b;

            const baseSize = randRange(1.5, 6.5) * lerp(0.6, 1.05, 1 - t * 0.45);
            sizes[idx] = baseSize;

            // Twinkling stars (~6%)
            if (Math.random() < 0.06) {
                this._twinkleData.push({
                    index: idx, baseSize,
                    phase: Math.random() * Math.PI * 2,
                    speed: randRange(0.5, 2.2),
                });
            }
        }

        const geo = new THREE.BufferGeometry();
        geo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        geo.setAttribute('color',    new THREE.BufferAttribute(colors,    3));
        geo.setAttribute('size',     new THREE.BufferAttribute(sizes,     1));

        const mat = new THREE.ShaderMaterial({
            uniforms: {
                uTexture:    { value: makeStarTexture(128) },
                uPixelRatio: { value: this.renderer.getPixelRatio() },
            },
            vertexShader: `
                attribute float size;
                attribute vec3  color;
                varying   vec3  vColor;
                uniform   float uPixelRatio;
                void main() {
                    vColor = color;
                    vec4 mvPos = modelViewMatrix * vec4(position, 1.0);
                    gl_PointSize = size * uPixelRatio * (300.0 / -mvPos.z);
                    gl_Position  = projectionMatrix * mvPos;
                }
            `,
            fragmentShader: `
                uniform sampler2D uTexture;
                varying vec3      vColor;
                void main() {
                    vec4 tex = texture2D(uTexture, gl_PointCoord);
                    if (tex.a < 0.015) discard;
                    // Boost brightness — critical for vivid stars
                    gl_FragColor = vec4(vColor * 2.2, tex.a * 0.95);
                }
            `,
            transparent:  true,
            depthWrite:   false,
            blending:     THREE.AdditiveBlending,
            vertexColors: true,
        });

        this.starPoints = new THREE.Points(geo, mat);
        this.scene.add(this.starPoints);
    }

    // ── Bright foreground star halos ─────────────────────────────────────────

    _buildBrightStars() {
        const count = this.opts.brightStarCount;
        const R     = this.opts.galaxyRadius;

        for (let i = 0; i < count; i++) {
            const arm    = Math.floor(Math.random() * this.opts.arms);
            const t      = Math.pow(Math.random(), 0.5);
            const r      = 0.8 + t * R * 0.9;
            const offset = (arm / this.opts.arms) * Math.PI * 2;
            const theta  = (Math.log(r / 0.45)) / this.opts.armTightness + offset;
            const scatter = (Math.random() - 0.5) * 2.5;

            const x = r * Math.cos(theta) + scatter * 0.3;
            const y = (Math.random() - 0.5) * 0.25;
            const z = r * Math.sin(theta) + scatter * 0.3;

            const colHex = BRIGHT_STARS[Math.floor(Math.random() * BRIGHT_STARS.length)];
            const sz     = randRange(0.08, 0.42);

            const sprite = new THREE.Sprite(new THREE.SpriteMaterial({
                map:         makeHaloTexture(128, colHex),
                blending:    THREE.AdditiveBlending,
                transparent: true,
                depthWrite:  false,
                opacity:     randRange(0.55, 0.95),
            }));
            sprite.scale.set(sz, sz, 1);
            sprite.position.set(x, y, z);
            this.scene.add(sprite);
        }
    }

    // ── Nebula clouds ─────────────────────────────────────────────────────────

    _buildNebulae() {
        const R = this.opts.galaxyRadius;

        for (let n = 0; n < this.opts.nebulaCount; n++) {
            const arm        = Math.floor(Math.random() * this.opts.arms);
            const armOffset  = (arm / this.opts.arms) * Math.PI * 2;
            const t          = randRange(0.22, 0.92);
            const r          = this.opts.coreRadius * 0.6 + t * (R * 0.90);
            const theta      = (Math.log(r / 0.45)) / this.opts.armTightness + armOffset;

            const x = r * Math.cos(theta) + randRange(-1.5, 1.5);
            const y = randRange(-0.3, 0.3);
            const z = r * Math.sin(theta) + randRange(-1.5, 1.5);

            const colorHex = NEBULA_COLORS[Math.floor(Math.random() * NEBULA_COLORS.length)];
            const scale    = randRange(2.5, 6.5);

            const sprite = new THREE.Sprite(new THREE.SpriteMaterial({
                map:         makeNebulaTexture(256, colorHex),
                blending:    THREE.AdditiveBlending,
                transparent: true,
                depthWrite:  false,
                opacity:     randRange(0.25, 0.58),
            }));
            sprite.position.set(x, y, z);
            sprite.scale.set(scale * randRange(0.75, 1.4), scale * randRange(0.65, 1.2), 1);
            this.scene.add(sprite);
        }
    }

    // ── Core glow (layered) ───────────────────────────────────────────────────

    _buildCoreGlow() {
        const layers = [
            { scale: 2.2,  opacity: 0.92, color: '#ffffff' },
            { scale: 4.0,  opacity: 0.65, color: '#fff8dc' },
            { scale: 7.5,  opacity: 0.38, color: '#ffd966' },
            { scale: 13.0, opacity: 0.18, color: '#ffc44d' },
            { scale: 22.0, opacity: 0.07, color: '#ff9f1c' },
        ];

        layers.forEach(({ scale, opacity, color }) => {
            const sprite = new THREE.Sprite(new THREE.SpriteMaterial({
                map:         makeHaloTexture(256, color),
                blending:    THREE.AdditiveBlending,
                transparent: true,
                depthWrite:  false,
                opacity,
            }));
            sprite.scale.set(scale, scale, 1);
            sprite.position.set(0, 0, 0);
            this.scene.add(sprite);
        });
    }

    // ── Dust lane ─────────────────────────────────────────────────────────────

    _buildDustLane() {
        const geo = new THREE.TorusGeometry(7.0, 0.45, 8, 80);
        const mat = new THREE.MeshBasicMaterial({
            color:       0x000308,
            transparent: true,
            opacity:     0.28,
            side:        THREE.DoubleSide,
            depthWrite:  false,
        });
        const dust = new THREE.Mesh(geo, mat);
        dust.rotation.x = Math.PI / 2;
        this.scene.add(dust);
    }

    // ── Animation ─────────────────────────────────────────────────────────────

    _animate() {
        if (this._disposed) return;
        this._rafId = requestAnimationFrame(() => this._animate());

        const delta = this._clock.getDelta();
        this._time += delta;

        const sizes = this.starPoints.geometry.attributes.size;
        this._twinkleData.forEach(({ index, baseSize, phase, speed }) => {
            sizes.array[index] = baseSize * (0.70 + 0.60 * Math.sin(this._time * speed * 2.5 + phase));
        });
        sizes.needsUpdate = true;

        if (this.controls) this.controls.update();
        this.renderer.render(this.scene, this.camera);
    }

    // ── Fly-through ───────────────────────────────────────────────────────────

    flyThrough(onComplete) {
        const start    = { z: this.camera.position.z, y: this.camera.position.y, fov: this.camera.fov };
        const duration = 1600;
        const begin    = performance.now();

        const tick = (now) => {
            const t = Math.min((now - begin) / duration, 1);
            const e = t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t;

            this.camera.position.z = lerp(start.z, -10, e);
            this.camera.position.y = lerp(start.y, 0,   e);
            this.camera.fov        = lerp(start.fov, 120, e);
            this.camera.updateProjectionMatrix();

            if (this.controls) this.controls.autoRotateSpeed = lerp(0.22, 6.0, e);

            if (t < 1) requestAnimationFrame(tick);
            else if (onComplete) onComplete();
        };
        requestAnimationFrame(tick);
    }

    // ── Resize ────────────────────────────────────────────────────────────────

    _handleResize() {
        const W = this.el.clientWidth;
        const H = this.el.clientHeight;
        if (!W || !H) return;
        this.camera.aspect = W / H;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(W, H);
    }

    // ── Dispose ───────────────────────────────────────────────────────────────

    dispose() {
        this._disposed = true;
        cancelAnimationFrame(this._rafId);
        window.removeEventListener('resize', this._onResize);
        if (this.controls) this.controls.dispose();
        this.renderer.dispose();
        if (this.renderer.domElement.parentNode) {
            this.renderer.domElement.parentNode.removeChild(this.renderer.domElement);
        }
    }
}
