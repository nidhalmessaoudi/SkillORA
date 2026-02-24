/**
 * Galaxy Admin — Interactive 3D user galaxy for the admin dashboard.
 * Standalone version (no module bundling required)
 * Each user = a vivid glowing star. Color-coded by role/status.
 */

// Wait for THREE to be loaded from CDN
window.initGalaxy = function() {
    if (typeof THREE === 'undefined') {
        console.error('THREE.js not loaded yet');
        return;
    }

    // ── Star profiles ─────────────────────────────────────────────────────────────
    const STAR_PROFILES = {
        admin:    { color: 0xffd700, size: 2.5, glowColor: '#ffd700', ring: '#ffe566', label: 'Admin'    },
        banned:   { color: 0xff3333, size: 2.0, glowColor: '#ff3333', ring: '#ff7777', label: 'Banned'   },
        new:      { color: 0x60a5fa, size: 2.2, glowColor: '#60a5fa', ring: '#93c5fd', label: 'New'      },
        active:   { color: 0x34d399, size: 2.3, glowColor: '#34d399', ring: '#6ee7b7', label: 'Active'   },
        inactive: { color: 0x6b7280, size: 1.8, glowColor: '#6b7280', ring: '#9ca3af', label: 'Inactive' },
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

    function makeBackgroundStarTexture() {
        const size = 64;
        const c = document.createElement('canvas');
        c.width = c.height = size;
        const ctx = c.getContext('2d');
        const h = size / 2;
        const g = ctx.createRadialGradient(h, h, 0, h, h, h);
        g.addColorStop(0.0, 'rgba(255,255,255,0.9)');
        g.addColorStop(0.3, 'rgba(199,210,254,0.5)');
        g.addColorStop(1.0, 'rgba(199,210,254,0.0)');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, size, size);
        return new THREE.CanvasTexture(c);
    }

    function makeCenterGlowTexture() {
        const size = 512;
        const c = document.createElement('canvas');
        c.width = c.height = size;
        const ctx = c.getContext('2d');
        const h = size / 2;
        const g = ctx.createRadialGradient(h, h, 0, h, h, h);
        g.addColorStop(0.00, 'rgba(139,92,246,0.6)');
        g.addColorStop(0.30, 'rgba(124,58,237,0.3)');
        g.addColorStop(0.65, 'rgba(109,40,217,0.08)');
        g.addColorStop(1.00, 'rgba(88,28,135,0.0)');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, size, size);
        return new THREE.CanvasTexture(c);
    }

    // ── AdminGalaxy class ─────────────────────────────────────────────────────────

    class AdminGalaxy {
        constructor(containerSelector) {
            this.container = document.querySelector(containerSelector);
            if (!this.container) {
                console.warn(`AdminGalaxy: container "${containerSelector}" not found`);
                return;
            }

            this._setupScene();
            this._setupCamera();
            this._setupRenderer();
            this._setupControls();
            this._buildBackgroundStars();
            this._buildCenterGlow();
            this._setupRaycaster();

            this.starMeshes = [];
            this.userCardEl = null;

            this._animate();
            this._onResize = () => this._handleResize();
            window.addEventListener('resize', this._onResize);
            this.container.addEventListener('click', (e) => this._onClick(e));
        }

        _setupScene() {
            this.scene = new THREE.Scene();
            this.scene.background = new THREE.Color(0x000000);
        }

        _setupCamera() {
            const aspect = this.container.clientWidth / this.container.clientHeight;
            this.camera = new THREE.PerspectiveCamera(60, aspect, 0.1, 1000);
            this.camera.position.set(0, 8, 25); // Closer view to see stars better
        }

        _setupRenderer() {
            this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false });
            this.renderer.setSize(this.container.clientWidth, this.container.clientHeight);
            this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
            this.container.appendChild(this.renderer.domElement);
        }

        _setupControls() {
            this.controls = new THREE.OrbitControls(this.camera, this.renderer.domElement);
            this.controls.enableDamping = true;
            this.controls.dampingFactor = 0.1;
            this.controls.enablePan = false;
            this.controls.minDistance = 10;
            this.controls.maxDistance = 50;
            this.controls.autoRotate = true;
            this.controls.autoRotateSpeed = 0.08; // Even slower for better visuals
        }

        _getStarProfile(user) {
            if (user.role === 'admin') return STAR_PROFILES.admin;
            if (user.banned)          return STAR_PROFILES.banned;
            if (user.daysOld < 7)     return STAR_PROFILES.new;
            if (user.daysOld > 90)    return STAR_PROFILES.inactive;
            return STAR_PROFILES.active;
        }

        _getPosition(index, total) {
            const golden = (1 + Math.sqrt(5)) / 2;
            const i = index + 0.5;
            const phi = Math.acos(1 - (2 * i) / Math.max(total, 1));
            const theta = (2 * Math.PI * i) / golden;
            const r = 8 + (index / Math.max(total, 1)) * 14; // Slightly wider spread

            return new THREE.Vector3(
                r * Math.sin(phi) * Math.cos(theta),
                r * Math.cos(phi) * 0.4, // Slightly more vertical
                r * Math.sin(phi) * Math.sin(theta)
            );
        }

        _createStar(profile, position, userData) {
            const sprite = new THREE.Sprite(new THREE.SpriteMaterial({
                map: makeStarSprite(profile.glowColor, 256),
                blending: THREE.AdditiveBlending,
                transparent: true,
                depthWrite: false,
                opacity: 0.92,
            }));

            const sz = profile.size;
            sprite.scale.set(sz, sz, 1);
            sprite.position.copy(position);
            sprite.userData = {
                userId: userData.id,
                userName: userData.name,
                userRole: userData.role,
                banned: userData.banned,
                daysOld: userData.daysOld,
                profile,
                baseScale: sz,
                phase: Math.random() * Math.PI * 2,
                pulseSpeed: 0.7 + Math.random() * 0.7,
            };
            return sprite;
        }

        loadUsers(users) {
            this.starMeshes.forEach(s => this.scene.remove(s));
            this.starMeshes = [];

            users.forEach((user, i) => {
                const profile = this._getStarProfile(user);
                const pos = this._getPosition(i, users.length);
                const star = this._createStar(profile, pos, user);
                this.scene.add(star);
                this.starMeshes.push(star);
            });
        }

        _buildBackgroundStars() {
            const count = 3000;
            const positions = new Float32Array(count * 3);
            for (let i = 0; i < count; i++) {
                // Spread stars throughout the entire space
                const r = 30 + Math.random() * 200;
                const theta = Math.random() * Math.PI * 2;
                const phi = Math.acos(2 * Math.random() - 1);
                positions[i * 3 + 0] = r * Math.sin(phi) * Math.cos(theta);
                positions[i * 3 + 1] = r * Math.sin(phi) * Math.sin(theta);
                positions[i * 3 + 2] = r * Math.cos(phi);
            }

            const geom = new THREE.BufferGeometry();
            geom.setAttribute('position', new THREE.BufferAttribute(positions, 3));

            const mat = new THREE.PointsMaterial({
                size: 0.08,
                transparent: true,
                depthWrite: false,
                color: 0xffffff,
                opacity: 0.6,
            });

            this.bgStars = new THREE.Points(geom, mat);
            this.scene.add(this.bgStars);
        }

        _buildCenterGlow() {
            // Removed - center glow is too visible and distracting
            // The reference image doesn't have a purple glow
        }

        _setupRaycaster() {
            this._raycaster = new THREE.Raycaster();
            this._mouse = new THREE.Vector2();
        }

        _onClick(e) {
            const rect = this.renderer.domElement.getBoundingClientRect();
            this._mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
            this._mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;
            this._raycaster.setFromCamera(this._mouse, this.camera);
            const hits = this._raycaster.intersectObjects(this.starMeshes);
            if (hits.length > 0) this._showUserCard(hits[0].object.userData);
            else this._hideUserCard();
        }

        _showUserCard(userData) {
            this._hideUserCard();

            const card = document.createElement('div');
            card.id = 'galaxy-user-card';
            card.style.cssText = `
                position: fixed; top: 80px; right: 20px; z-index: 9999;
                background: rgba(6,6,22,0.95); backdrop-filter: blur(12px);
                border: 2px solid ${userData.profile.glowColor}; border-radius: 16px;
                padding: 20px; width: 280px; box-shadow: 0 20px 60px rgba(0,0,0,0.5);
                color: white; font-family: system-ui; animation: cardIn 0.3s ease;
            `;

            const statusColor = userData.banned ? '#ff3333' :
                               userData.userRole === 'admin' ? '#ffd700' : '#34d399';

            card.innerHTML = `
                <style>
                    @keyframes cardIn {
                        from { opacity:0; transform:scale(0.8) translateY(-12px); }
                        to { opacity:1; transform:scale(1) translateY(0); }
                    }
                </style>
                <button onclick="document.getElementById('galaxy-user-card').remove()" 
                        style="position:absolute; top:12px; right:12px; background:rgba(255,255,255,0.1); 
                               border:none; color:#fff; font-size:20px; cursor:pointer; width:28px; height:28px; 
                               border-radius:50%; line-height:1;">×</button>
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                    <div style="width:48px; height:48px; border-radius:50%; background:${userData.profile.glowColor}; 
                                display:flex; align-items:center; justify-content:center; font-size:20px; font-weight:bold;">
                        ${userData.userName.charAt(0).toUpperCase()}
                    </div>
                    <div style="flex:1; min-width:0;">
                        <div style="font-size:16px; font-weight:700; color:#fff; margin-bottom:4px; 
                                    overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            ${userData.userName}
                        </div>
                        <div style="display:inline-block; padding:2px 8px; background:${statusColor}33; 
                                    color:${statusColor}; border-radius:6px; font-size:11px; font-weight:600;">
                            ${userData.profile.label}
                        </div>
                    </div>
                </div>
                <div style="border-top:1px solid rgba(255,255,255,0.1); padding-top:12px; font-size:13px; color:#cbd5e1;">
                    <div style="margin-bottom:8px;">
                        <span style="color:#94a3b8;">Account Age:</span> 
                        <span style="color:#fff; font-weight:600;">${userData.daysOld} days</span>
                    </div>
                    <div style="margin-bottom:8px;">
                        <span style="color:#94a3b8;">User ID:</span> 
                        <span style="color:#fff; font-weight:600;">#${userData.userId}</span>
                    </div>
                    ${userData.banned ? '<div style="color:#ff3333; margin-top:12px; font-weight:600;">⚠️ Account Banned</div>' : ''}
                </div>
            `;

            document.body.appendChild(card);
            this.userCardEl = card;
        }

        _hideUserCard() {
            if (this.userCardEl) {
                this.userCardEl.remove();
                this.userCardEl = null;
            }
        }

        _animate() {
            requestAnimationFrame(() => this._animate());
            const t = performance.now() * 0.001;

            this.starMeshes.forEach(star => {
                const { baseScale, phase, pulseSpeed, profile } = star.userData;
                const pulse = 1 + 0.12 * Math.sin(t * pulseSpeed * 0.3 + phase); // Very slow, subtle pulse
                star.scale.setScalar(baseScale * pulse);
                if (profile === STAR_PROFILES.admin) {
                    star.material.opacity = 0.88 + 0.12 * Math.sin(t * 1.0 + phase); // Very slow opacity pulse
                }
            });

            // No background rotation - keep stars static
            this.controls.update();
            this.renderer.render(this.scene, this.camera);
        }

        _handleResize() {
            if (!this.container.clientWidth) return;
            this.camera.aspect = this.container.clientWidth / this.container.clientHeight;
            this.camera.updateProjectionMatrix();
            this.renderer.setSize(this.container.clientWidth, this.container.clientHeight);
        }

        dispose() {
            window.removeEventListener('resize', this._onResize);
            this.controls.dispose();
            this.renderer.dispose();
        }
    }

    // ── Initialize galaxy on page load ───────────────────────────────────────────

    const galaxy = new AdminGalaxy('#admin-galaxy-container');

    fetch('/galaxy/users-data', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(users => {
            galaxy.loadUsers(users);
            const countEl = document.getElementById('admin-galaxy-user-count');
            if (countEl) countEl.textContent = users.length;
        })
        .catch(err => {
            console.warn('Galaxy: could not load user data', err);
            const placeholder = Array.from({ length: 20 }, (_, i) => ({
                id: i, name: 'User ' + (i + 1), role: i === 0 ? 'admin' : 'user',
                banned: false, daysOld: Math.floor(Math.random() * 120),
            }));
            galaxy.loadUsers(placeholder);
            const countEl = document.getElementById('admin-galaxy-user-count');
            if (countEl) countEl.textContent = '20 (demo)';
        });
};
