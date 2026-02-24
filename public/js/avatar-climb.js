/**
 * 3D Avatar Display for Profile
 * Shows user's selected avatar with climbing/entrance animation
 */

class ProfileAvatar {
    constructor(containerId, avatarFile) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.error('Profile avatar container not found:', containerId);
            return;
        }

        if (!avatarFile) {
            console.warn('No avatar file provided');
            return;
        }

        this.avatarFile = avatarFile;
        this.scene = null;
        this.camera = null;
        this.renderer = null;
        this.model = null;
        this.mixer = null;
        this.clock = new THREE.Clock();
        this.animationId = null;

        this.init();
    }

    init() {
        this.createScene();
        this.loadAvatar();
        this.startAnimation();
    }

    createScene() {
        // Create scene
        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(0x0f172a); // Dark navy background

        // Create camera
        const width = this.container.clientWidth;
        const height = this.container.clientHeight;
        this.camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 1000);
        this.camera.position.set(0, 1.2, 2.5);
        this.camera.lookAt(0, 0.8, 0);

        // Create renderer
        const canvas = document.createElement('canvas');
        this.renderer = new THREE.WebGLRenderer({ 
            canvas: canvas, 
            antialias: true,
            alpha: true 
        });
        this.renderer.setSize(width, height);
        this.renderer.setPixelRatio(window.devicePixelRatio);
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;

        this.container.appendChild(canvas);

        // Add lighting setup
        this.setupLighting();

        // Handle window resize
        window.addEventListener('resize', () => this.onWindowResize());
    }

    setupLighting() {
        // Ambient light for overall illumination
        const ambientLight = new THREE.AmbientLight(0xffffff, 0.7);
        this.scene.add(ambientLight);

        // Main directional light (key light)
        const mainLight = new THREE.DirectionalLight(0xffffff, 0.9);
        mainLight.position.set(3, 4, 2);
        mainLight.castShadow = true;
        mainLight.shadow.camera.near = 0.1;
        mainLight.shadow.camera.far = 10;
        mainLight.shadow.mapSize.width = 1024;
        mainLight.shadow.mapSize.height = 1024;
        this.scene.add(mainLight);

        // Fill light (purple accent from left)
        const fillLight = new THREE.DirectionalLight(0x8b5cf6, 0.5);
        fillLight.position.set(-3, 2, -1);
        this.scene.add(fillLight);

        // Rim light (cyan accent from back)
        const rimLight = new THREE.DirectionalLight(0x06b6d4, 0.4);
        rimLight.position.set(0, 3, -3);
        this.scene.add(rimLight);

        // Subtle point light beneath for glow effect
        const bottomLight = new THREE.PointLight(0x6366f1, 0.3, 5);
        bottomLight.position.set(0, -0.5, 0);
        this.scene.add(bottomLight);
    }

    loadAvatar() {
        const loader = new window.GLTFLoader();
        
        loader.load(
            `/avatars/${this.avatarFile}`,
            (gltf) => {
                this.model = gltf.scene;
                
                // Start position below the frame (for climb animation)
                this.model.position.set(0, -3, 0);
                this.model.scale.set(1, 1, 1);
                
                // Enable shadows
                this.model.traverse((node) => {
                    if (node.isMesh) {
                        node.castShadow = true;
                        node.receiveShadow = true;
                    }
                });

                this.scene.add(this.model);

                // Setup animations if available
                if (gltf.animations && gltf.animations.length > 0) {
                    this.mixer = new THREE.AnimationMixer(this.model);
                    const action = this.mixer.clipAction(gltf.animations[0]);
                    action.play();
                }

                // Trigger climb animation
                this.playClimbAnimation();
            },
            (progress) => {
                // Loading progress
                const percent = (progress.loaded / progress.total) * 100;
                console.log(`Loading avatar: ${percent.toFixed(0)}%`);
            },
            (error) => {
                console.error('Error loading avatar:', error);
                this.showFallback();
            }
        );
    }

    playClimbAnimation() {
        if (!this.model) return;

        const duration = 1200; // 1.2 seconds
        const startY = -3;
        const endY = 0;
        const startTime = Date.now();

        const animate = () => {
            const elapsed = Date.now() - startTime;
            const progress = Math.min(elapsed / duration, 1);

            // Easing function (ease-out cubic for smooth deceleration)
            const eased = 1 - Math.pow(1 - progress, 3);

            // Update position
            this.model.position.y = startY + (endY - startY) * eased;

            // Add slight rotation during climb
            this.model.rotation.y = Math.sin(progress * Math.PI) * 0.2;

            // Add bounce at the end
            if (progress > 0.8) {
                const bounceProgress = (progress - 0.8) / 0.2;
                const bounce = Math.sin(bounceProgress * Math.PI) * 0.1;
                this.model.position.y += bounce;
            }

            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                // Reset rotation after climb
                this.model.rotation.y = 0;
            }
        };

        animate();
    }

    startAnimation() {
        const animate = () => {
            this.animationId = requestAnimationFrame(animate);

            if (this.model) {
                // Gentle idle rotation
                this.model.rotation.y += 0.003;

                // Subtle breathing animation
                const time = Date.now() * 0.001;
                this.model.position.y += Math.sin(time * 2) * 0.0005;

                // Update model animations
                if (this.mixer) {
                    const delta = this.clock.getDelta();
                    this.mixer.update(delta);
                }
            }

            this.renderer.render(this.scene, this.camera);
        };

        animate();
    }

    onWindowResize() {
        const width = this.container.clientWidth;
        const height = this.container.clientHeight;

        this.camera.aspect = width / height;
        this.camera.updateProjectionMatrix();

        this.renderer.setSize(width, height);
    }

    showFallback() {
        // Show fallback UI if avatar fails to load
        this.container.innerHTML = `
            <div style="
                width: 100%;
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 1rem;
            ">
                <div style="text-align: center; color: white;">
                    <i data-lucide="user" style="width: 48px; height: 48px; margin: 0 auto;"></i>
                    <p style="margin-top: 0.5rem; font-size: 0.875rem;">Avatar unavailable</p>
                </div>
            </div>
        `;
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    destroy() {
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
        }

        if (this.renderer) {
            this.renderer.dispose();
        }

        if (this.scene) {
            this.scene.traverse((object) => {
                if (object.geometry) {
                    object.geometry.dispose();
                }
                if (object.material) {
                    if (Array.isArray(object.material)) {
                        object.material.forEach(material => material.dispose());
                    } else {
                        object.material.dispose();
                    }
                }
            });
        }

        window.removeEventListener('resize', this.onWindowResize);
    }
}

// Auto-initialize if data attributes are present
document.addEventListener('DOMContentLoaded', () => {
    console.log('Profile avatar: DOM loaded');
    const containers = document.querySelectorAll('[data-profile-avatar]');
    console.log('Found', containers.length, 'profile avatar containers');
    
    containers.forEach(container => {
        const avatarFile = container.dataset.avatarFile;
        console.log('Container:', container.id, 'Avatar file:', avatarFile);
        if (avatarFile) {
            console.log('Initializing profile avatar for', container.id);
            new ProfileAvatar(container.id, avatarFile);
        }
    });
});
