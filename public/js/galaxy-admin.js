/**
 * Galaxy Admin — Interactive 3D user galaxy for the admin dashboard.
 * Each user = a vivid glowing star.
 * Color-coded by role/status. Click = info card. Drag to orbit.
 */

import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';

// ── Star profiles ─────────────────────────────────────────────────────────────
const STAR_PROFILES = {
    admin:    { color: 0xffd700, size: 1.8, glowColor: '#ffd700', ring: '#ffe566', label: 'Admin'    },
    banned:   { color: 0xff3333, size: 1.1, glowColor: '#ff3333', ring: '#ff7777', label: 'Banned'   },
    new:      { color: 0x60a5fa, size: 1.2, glowColor: '#60a5fa', ring: '#93c5fd', label: 'New'      },
    active:   { color: 0x34d399, size: 1.4, glowColor: '#34d399', ring: '#6ee7b7', label: 'Active'   },
    inactive: { color: 0x6b7280, size: 0.9, glowColor: '#6b7280', ring: '#9ca3af', label: 'Inactive' },
};

// ── Texture helpers ───────────────────────────────────────────────────────────

function makeStarSprite(hex, size = 256) {
    const c   = document.createElement('canvas');
    c.width   = c.height = size;
    const ctx = c.getContext('2d');
    const h   = size / 2;
    const col = new THREE.Color(hex);
    const r   = Math.round(col.r * 255);
    const g   = Math.round(col.g * 255);
    const b   = Math.round(col.b * 255);

    // Outer soft halo
    const g1 = ctx.createRadialGradient(h, h, 0, h, h, h);
    g1.addColorStop(0.00, `rgba(${r},${g},${b},1.0)`);
    g1.addColorStop(0.08, `rgba(${r},${g},${b},0.95)`);
    g1.addColorStop(0.22, `rgba(${r},${g},${b},0.55)`);
    g1.addColorStop(0.50, `rgba(${r},${g},${b},0.15)`);
    g1.addColorStop(1.00, `rgba(${r},${g},${b},0.00)`);
    ctx.fillStyle = g1;
    ctx.fillRect(0, 0, size, size);

    // Bright white center dot
    const g2 = ctx.createRadialGradient(h, h, 0, h, h, h * 0.12);
    g2.addColorStop(0, 'rgba(255,255,255,1.0)');
    g2.addColorStop(1, 'rgba(255,255,255,0.0)');
    ctx.fillStyle = g2;
    ctx.fillRect(0, 0, size, size);

    return new THREE.CanvasTexture(c);
}

function makeNebulaSprite(hex, size = 512) {
    const c   = document.createElement('canvas');
    c.width   = c.height = size;
    const ctx = c.getContext('2d');
    const col = new THREE.Color(hex);
    const r   = Math.round(col.r * 255);
    const g   = Math.round(col.g * 255);
    const b   = Math.round(col.b * 255);
    const h   = size / 2;

    for (let i = 0; i < 4; i++) {
        const ox = h * (0.3 + Math.random() * 0.4);
        const oy = h * (0.3 + Math.random() * 0.4);
        const rd = h * (0.5 + Math.random() * 0.5);
        const gd = ctx.createRadialGradient(ox, oy, 0, ox, oy, rd);
        gd.addColorStop(0.0, `rgba(${r},${g},${b},0.22)`);
        gd.addColorStop(0.5, `rgba(${r},${g},${b},0.08)`);
        gd.addColorStop(1.0, `rgba(${r},${g},${b},0.00)`);
        ctx.fillStyle = gd;
        ctx.fillRect(0, 0, size, size);
    }
    return new THREE.CanvasTexture(c);
}

// ── AdminGalaxy class ─────────────────────────────────────────────────────────

class AdminGalaxy {
    constructor(container) {
        this.container    = container;
        this.users        = [];
        this.starMeshes   = [];
        this.selectedCard = null;
        this._raycaster   = new THREE.Raycaster();
        this._mouse       = new THREE.Vector2();
        this._init();
    }

    _init() {
        const W = this.container.clientWidth  || 900;
        const H = this.container.clientHeight || 520;

        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(0x00000a);

        this.camera = new THREE.PerspectiveCamera(55, W / H, 0.1, 500);
        this.camera.position.set(0, 12, 30);
        this.camera.lookAt(0, 0, 0);

        this.renderer = new THREE.WebGLRenderer({ antialias: true });
        this.renderer.setSize(W, H);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.container.appendChild(this.renderer.domElement);

        this.controls = new OrbitControls(this.camera, this.renderer.domElement);
        this.controls.enableDamping   = true;
        this.controls.dampingFactor   = 0.05;
        this.controls.autoRotate      = true;
        this.controls.autoRotateSpeed = 0.30;
        this.controls.enablePan       = false;
        this.controls.minDistance     = 6;
        this.controls.maxDistance     = 70;

        this._buildBgStars();
        // Nebulae removed for cleaner look
        this._buildCentralGlow();

        this.renderer.domElement.addEventListener('click', this._onClick.bind(this));
        window.addEventListener('resize', () => {
            const nW = this.container.clientWidth;
            const nH = this.container.clientHeight;
            if (!nW || !nH) return;
            this.camera.aspect = nW / nH;
            this.camera.updateProjectionMatrix();
            this.renderer.setSize(nW, nH);
        });

        this._clock = new THREE.Clock();
        this._animate();
    }

    loadUsers(users) {
        this.users = users;
        this.starMeshes.forEach(m => this.scene.remove(m));
        this.starMeshes = [];

        users.forEach((user, i) => {
            const profile = this._getStarProfile(user);
            const pos     = this._getPosition(i, users.length);
            const star    = this._createStar(profile, pos, user);
            this.scene.add(star);
            this.starMeshes.push(star);
        });
    }

    _getStarProfile(user) {
        if (user.role === 'admin') return STAR_PROFILES.admin;
        if (user.banned)          return STAR_PROFILES.banned;
        if (user.daysOld < 7)     return STAR_PROFILES.new;
        if (user.daysOld > 90)    return STAR_PROFILES.inactive;
        return STAR_PROFILES.active;
    }

    _getPosition(index, total) {
        // Fibonacci sphere → flatten into disk
        const golden = (1 + Math.sqrt(5)) / 2;
        const i      = index + 0.5;
        const phi    = Math.acos(1 - (2 * i) / Math.max(total, 1));
        const theta  = (2 * Math.PI * i) / golden;
        const r      = 5 + (index / Math.max(total, 1)) * 10;

        return new THREE.Vector3(
            r * Math.sin(phi) * Math.cos(theta),
            r * Math.cos(phi) * 0.35,
            r * Math.sin(phi) * Math.sin(theta)
        );
    }

    _createStar(profile, position, userData) {
        const sprite = new THREE.Sprite(new THREE.SpriteMaterial({
            map:         makeStarSprite(profile.glowColor, 256),
            blending:    THREE.AdditiveBlending,
            transparent: true,
            depthWrite:  false,
            opacity:     0.92,
        }));

        const sz = profile.size;
        sprite.scale.set(sz, sz, 1);
        sprite.position.copy(position);
        sprite.userData = {
            userId:     userData.id,
            userName:   userData.name,
            userRole:   userData.role,
            banned:     userData.banned,
            daysOld:    userData.daysOld,
            profile,
            baseScale:  sz,
            phase:      Math.random() * Math.PI * 2,
            pulseSpeed: 0.7 + Math.random() * 0.7,
        };
        return sprite;
    }

    // ── Background decoration ─────────────────────────────────────────────────

    _buildBgStars() {
        const count = 8000;
        const pos   = new Float32Array(count * 3);
        for (let i = 0; i < count; i++) {
            const theta = Math.random() * Math.PI * 2;
            const phi   = Math.acos(2 * Math.random() - 1);
            const r     = 90 + Math.random() * 120;
            pos[i*3]     = r * Math.sin(phi) * Math.cos(theta);
            pos[i*3 + 1] = r * Math.cos(phi);
            pos[i*3 + 2] = r * Math.sin(phi) * Math.sin(theta);
        }
        const geo = new THREE.BufferGeometry();
        geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
        const mat = new THREE.PointsMaterial({
            size: 0.18, color: 0xc7d2fe, transparent: true,
            opacity: 0.65, blending: THREE.AdditiveBlending, depthWrite: false,
        });
        this.scene.add(new THREE.Points(geo, mat));
    }

    _buildNebulae() {
        const colors = ['#4c1d95', '#1e3a8a', '#7f1d1d', '#0c4a6e', '#064e3b', '#3b0764'];
        colors.forEach((col, i) => {
            const sprite = new THREE.Sprite(new THREE.SpriteMaterial({
                map:      makeNebulaSprite(col),
                blending: THREE.AdditiveBlending, transparent: true, depthWrite: false,
                opacity:  0.28 + Math.random() * 0.18,
            }));
            const angle = (i / colors.length) * Math.PI * 2;
            const r     = 9 + Math.random() * 7;
            sprite.position.set(
                Math.cos(angle) * r,
                (Math.random() - 0.5) * 4,
                Math.sin(angle) * r
            );
            const sz = 16 + Math.random() * 12;
            sprite.scale.set(sz, sz * 0.65, 1);
            this.scene.add(sprite);
        });
    }

    _buildCentralGlow() {
        // Subtle galactic core glow
        [
            { scale: 3.0, opacity: 0.30, color: '#7c3aed' },
            { scale: 7.0, opacity: 0.12, color: '#4c1d95' },
        ].forEach(({ scale, opacity, color }) => {
            const c   = document.createElement('canvas');
            c.width   = c.height = 256;
            const ctx = c.getContext('2d');
            const col = new THREE.Color(color);
            const r   = Math.round(col.r * 255);
            const g   = Math.round(col.g * 255);
            const b   = Math.round(col.b * 255);
            const gd  = ctx.createRadialGradient(128, 128, 0, 128, 128, 128);
            gd.addColorStop(0, `rgba(${r},${g},${b},1)`);
            gd.addColorStop(1, `rgba(${r},${g},${b},0)`);
            ctx.fillStyle = gd;
            ctx.fillRect(0, 0, 256, 256);
            const sp = new THREE.Sprite(new THREE.SpriteMaterial({
                map: new THREE.CanvasTexture(c),
                blending: THREE.AdditiveBlending, transparent: true, depthWrite: false, opacity,
            }));
            sp.scale.set(scale, scale, 1);
            this.scene.add(sp);
        });
    }

    // ── Click handling ────────────────────────────────────────────────────────

    _onClick(e) {
        const rect = this.renderer.domElement.getBoundingClientRect();
        this._mouse.x =  ((e.clientX - rect.left) / rect.width)  * 2 - 1;
        this._mouse.y = -((e.clientY - rect.top)  / rect.height) * 2 + 1;
        this._raycaster.setFromCamera(this._mouse, this.camera);
        const hits = this._raycaster.intersectObjects(this.starMeshes);
        if (hits.length > 0) this._showUserCard(hits[0].object.userData);
        else this._hideUserCard();
    }

    _showUserCard(ud) {
        this._hideUserCard();
        const card = document.createElement('div');
        card.id = 'admin-galaxy-card';

        const c       = ud.profile.glowColor;
        const ring    = ud.profile.ring;
        const status  = ud.banned ? 'Banned' :
                        ud.userRole === 'admin' ? 'Admin' :
                        ud.daysOld < 7 ? 'New User' :
                        ud.daysOld > 90 ? 'Inactive' : 'Active';

        card.innerHTML = `
            <div style="
                background: rgba(6,6,22,0.95);
                border: 1px solid ${c}55;
                border-radius: 18px; padding: 22px 24px;
                min-width: 210px; font-family: system-ui,sans-serif;
                box-shadow: 0 0 40px ${c}30, 0 8px 32px rgba(0,0,0,0.7);
                backdrop-filter: blur(20px); position: relative;
                animation: cardIn 0.28s cubic-bezier(0.34,1.56,0.64,1);">
                <button onclick="document.getElementById('admin-galaxy-card')?.remove()"
                        style="position:absolute;top:10px;right:14px;background:none;border:none;
                               color:#64748b;font-size:1.3rem;cursor:pointer;line-height:1;"
                        onmouseover="this.style.color='#e2e8f0'"
                        onmouseout="this.style.color='#64748b'">×</button>
                <!-- Star icon -->
                <div style="width:56px;height:56px;border-radius:50%;margin-bottom:14px;
                            background:radial-gradient(circle,${c}40,transparent 70%);
                            display:flex;align-items:center;justify-content:center;
                            border:1px solid ${c}40;">
                    <div style="width:18px;height:18px;border-radius:50%;
                                background:${c};box-shadow:0 0 16px ${c},0 0 32px ${c}88;"></div>
                </div>
                <div style="font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:3px;">
                    ${ud.userName}
                </div>
                <div style="font-size:0.68rem;font-weight:800;letter-spacing:0.14em;
                            text-transform:uppercase;color:${c};margin-bottom:10px;">
                    ${ud.userRole}
                </div>
                <div style="display:inline-block;padding:3px 12px;border-radius:20px;
                            font-size:0.75rem;font-weight:700;margin-bottom:12px;
                            background:${c}22;color:${ring};border:1px solid ${c}44;">
                    ${status}
                </div>
                <div style="font-size:0.72rem;color:#475569;">
                    Member for <strong style="color:#94a3b8;">${ud.daysOld}</strong> day${ud.daysOld !== 1 ? 's' : ''}
                </div>
            </div>`;

        card.style.cssText = 'position:absolute;z-index:100;top:20px;right:20px;';
        const parent = this.renderer.domElement.parentElement;
        if (getComputedStyle(parent).position === 'static') parent.style.position = 'relative';
        parent.appendChild(card);
        this.selectedCard = card;
    }

    _hideUserCard() {
        if (this.selectedCard) { this.selectedCard.remove(); this.selectedCard = null; }
    }

    // ── Animate ───────────────────────────────────────────────────────────────

    _animate() {
        requestAnimationFrame(() => this._animate());
        const t = this._clock.getElapsedTime();

        this.starMeshes.forEach(star => {
            const { baseScale, phase, pulseSpeed, profile } = star.userData;
            const pulse = 1 + 0.22 * Math.sin(t * pulseSpeed + phase);
            star.scale.setScalar(baseScale * pulse);
            if (profile === STAR_PROFILES.admin) {
                star.material.opacity = 0.78 + 0.22 * Math.sin(t * 3.0 + phase);
            }
        });

        this.controls.update();
        this.renderer.render(this.scene, this.camera);
    }
}

// ── Boot ──────────────────────────────────────────────────────────────────────

function injectCardStyles() {
    if (document.getElementById('admin-galaxy-styles')) return;
    const s = document.createElement('style');
    s.id = 'admin-galaxy-styles';
    s.textContent = `
        @keyframes cardIn {
            from { opacity:0; transform:scale(0.8) translateY(-12px); }
            to   { opacity:1; transform:scale(1)   translateY(0); }
        }
    `;
    document.head.appendChild(s);
}

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('admin-galaxy-container');
    if (!container) return;

    injectCardStyles();
    const galaxy = new AdminGalaxy(container);

    fetch('/galaxy/users-data', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(users => {
            galaxy.loadUsers(users);
            const countEl = document.getElementById('admin-galaxy-user-count');
            if (countEl) countEl.textContent = users.length;
        })
        .catch(err => {
            console.warn('Galaxy: could not load user data', err);
            // Show placeholder stars so the galaxy isn't empty
            const placeholder = Array.from({ length: 20 }, (_, i) => ({
                id: i, name: 'User ' + (i + 1), role: 'user',
                banned: false, daysOld: Math.floor(Math.random() * 120),
            }));
            galaxy.loadUsers(placeholder);
        });

    window._adminGalaxy = galaxy;
});
