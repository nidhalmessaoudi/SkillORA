/**
 * 3D Avatar Gallery Selector
 * Displays interactive 3D avatars for user selection during registration
 */

class AvatarSelector {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.error('Avatar container not found:', containerId);
            return;
        }

        this.avatars = [];
        this.scenes = [];
        this.cameras = [];
        this.renderers = [];
        this.selectedAvatar = null;
        
        // Available avatar files
        this.avatarFiles = {
            male: [
                'male-avatar.glb',
                'male-avatar1.glb',
                'male-avatar2.glb',
                'male-avatar3.glb',
                'male-avatar4.glb',
                'male-avatar5.glb',
                'male-avatar6.glb',
                'male-avatar7.glb'
            ],
            female: [
                'female-avatar.glb',
                'female-avatar2.glb',
                'female-avatar3.glb'
            ]
        };

        this.init();
    }

    init() {
        this.createAvatarGrid();
        this.setupEventListeners();
        this.startAnimationLoop();
    }

    createAvatarGrid() {
        // Create header section
        const header = document.createElement('div');
        header.style.cssText = `
            text-align: center;
            margin-bottom: 2rem;
        `;
        header.innerHTML = `
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem;">
                Choose Your 3D Avatar
            </h2>
            <p style="color: #64748b; font-size: 0.875rem;">
                Select an avatar that represents you in the SkillHarbor community
            </p>
        `;
        this.container.appendChild(header);

        // Create gender filter tabs
        const filterTabs = document.createElement('div');
        filterTabs.style.cssText = `
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            margin-bottom: 2rem;
        `;
        
        const allTab = this.createFilterTab('All', 'all', true);
        const maleTab = this.createFilterTab('Male', 'male', false);
        const femaleTab = this.createFilterTab('Female', 'female', false);
        
        filterTabs.appendChild(allTab);
        filterTabs.appendChild(maleTab);
        filterTabs.appendChild(femaleTab);
        this.container.appendChild(filterTabs);

        // Create grid container
        const grid = document.createElement('div');
        grid.className = 'avatar-grid';
        grid.id = 'avatar-grid';
        grid.style.cssText = `
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1.5rem;
            padding: 0.5rem;
            max-width: 1000px;
            margin: 0 auto;
        `;

        // Combine all avatars
        const allAvatars = [
            ...this.avatarFiles.male.map(file => ({ file, gender: 'male' })),
            ...this.avatarFiles.female.map(file => ({ file, gender: 'female' }))
        ];

        // Create card for each avatar
        allAvatars.forEach((avatar, index) => {
            const card = this.createAvatarCard(avatar, index);
            grid.appendChild(card);
        });

        this.container.appendChild(grid);
    }

    createFilterTab(label, filter, active) {
        const tab = document.createElement('button');
        tab.type = 'button';
        tab.className = `filter-tab ${active ? 'active' : ''}`;
        tab.dataset.filter = filter;
        tab.textContent = label;
        tab.style.cssText = `
            padding: 0.5rem 1.5rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            border: 2px solid ${active ? '#6366f1' : '#e2e8f0'};
            background: ${active ? '#6366f1' : 'white'};
            color: ${active ? 'white' : '#64748b'};
            cursor: pointer;
            transition: all 0.3s ease;
        `;
        
        tab.addEventListener('click', () => {
            this.filterAvatars(filter);
            
            // Update tab styles
            document.querySelectorAll('.filter-tab').forEach(t => {
                const isActive = t === tab;
                t.style.border = `2px solid ${isActive ? '#6366f1' : '#e2e8f0'}`;
                t.style.background = isActive ? '#6366f1' : 'white';
                t.style.color = isActive ? 'white' : '#64748b';
            });
        });
        
        tab.addEventListener('mouseenter', function() {
            if (!this.classList.contains('active')) {
                this.style.borderColor = '#6366f1';
                this.style.color = '#6366f1';
            }
        });
        
        tab.addEventListener('mouseleave', function() {
            if (!this.classList.contains('active')) {
                this.style.borderColor = '#e2e8f0';
                this.style.color = '#64748b';
            }
        });
        
        return tab;
    }

    filterAvatars(filter) {
        const cards = document.querySelectorAll('.avatar-card');
        cards.forEach(card => {
            const gender = card.dataset.gender;
            if (filter === 'all' || gender === filter) {
                card.style.display = 'block';
                card.style.animation = 'fadeIn 0.3s ease';
            } else {
                card.style.display = 'none';
            }
        });
    }

    createAvatarCard(avatar, index) {
        const card = document.createElement('div');
        card.className = 'avatar-card';
        card.dataset.index = index;
        card.dataset.file = avatar.file;
        card.dataset.gender = avatar.gender;
        card.style.cssText = `
            background: white;
            border-radius: 1.25rem;
            padding: 1.25rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            border: 3px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        `;

        // Canvas container with gradient background
        const canvasContainer = document.createElement('div');
        canvasContainer.style.cssText = `
            width: 100%;
            height: 220px;
            background: linear-gradient(135deg, #f0f4ff 0%, #e0e7ff 100%);
            border-radius: 0.75rem;
            position: relative;
            overflow: hidden;
            margin-bottom: 0.75rem;
        `;
        
        // Canvas for 3D avatar
        const canvas = document.createElement('canvas');
        canvas.width = 220;
        canvas.height = 220;
        canvas.style.cssText = `
            width: 100%;
            height: 100%;
            display: block;
        `;
        canvasContainer.appendChild(canvas);
        
        // Loading spinner
        const spinner = document.createElement('div');
        spinner.className = 'avatar-loading';
        spinner.innerHTML = `
            <div class="spinner"></div>
            <p style="color: white; margin-top: 0.5rem; font-size: 0.75rem;">Loading...</p>
        `;
        spinner.style.cssText = `
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(99, 102, 241, 0.9);
            backdrop-filter: blur(4px);
        `;
        canvasContainer.appendChild(spinner);
        card.appendChild(canvasContainer);

        // Avatar label with icon
        const label = document.createElement('div');
        label.className = 'avatar-label';
        const icon = avatar.gender === 'male' ? '👨' : '👩';
        const genderText = avatar.gender === 'male' ? 'Male' : 'Female';
        const avatarNumber = avatar.file.match(/\d+/) ? ` #${avatar.file.match(/\d+/)[0]}` : '';
        label.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <span style="font-size: 0.875rem; font-weight: 600; color: #1e293b;">
                    ${icon} ${genderText} Avatar${avatarNumber}
                </span>
            </div>
        `;
        label.style.cssText = `
            text-align: left;
            padding: 0.25rem 0;
        `;
        card.appendChild(label);

        // Checkmark for selection (updated position)
        const checkmark = document.createElement('div');
        checkmark.className = 'avatar-checkmark';
        checkmark.innerHTML = `
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        `;
        checkmark.style.cssText = `
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
            z-index: 10;
            border: 3px solid white;
        `;
        card.appendChild(checkmark);

        // Initialize Three.js scene for this avatar
        this.initAvatarScene(canvas, avatar.file, index);

        return card;
    }

    initAvatarScene(canvas, avatarFile, index) {
        // Create scene
        const scene = new THREE.Scene();
        scene.background = new THREE.Color(0x1a1a2e);

        // Create camera
        const camera = new THREE.PerspectiveCamera(45, 1, 0.1, 1000);
        camera.position.set(0, 1.2, 3);
        camera.lookAt(0, 0.9, 0);

        // Create renderer
        const renderer = new THREE.WebGLRenderer({ 
            canvas: canvas, 
            antialias: true,
            alpha: true 
        });
        renderer.setSize(220, 220);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.shadowMap.enabled = true;
        renderer.shadowMap.type = THREE.PCFSoftShadowMap;

        // Add enhanced lighting setup
        const ambientLight = new THREE.AmbientLight(0xffffff, 0.8);
        scene.add(ambientLight);

        // Main key light
        const directionalLight = new THREE.DirectionalLight(0xffffff, 1.0);
        directionalLight.position.set(3, 4, 3);
        directionalLight.castShadow = true;
        directionalLight.shadow.mapSize.width = 1024;
        directionalLight.shadow.mapSize.height = 1024;
        scene.add(directionalLight);

        // Fill light (purple accent)
        const fillLight = new THREE.DirectionalLight(0x8b5cf6, 0.3);
        fillLight.position.set(-2, 2, -1);
        scene.add(fillLight);

        // Rim light for depth
        const rimLight = new THREE.DirectionalLight(0x60a5fa, 0.2);
        rimLight.position.set(0, 2, -3);
        scene.add(rimLight);

        // Load avatar model
        const loader = new window.GLTFLoader();
        console.log('Loading avatar:', avatarFile, 'at index:', index);
        
        loader.load(
            `/avatars/${avatarFile}`,
            (gltf) => {
                console.log('Avatar loaded successfully:', avatarFile);
                
                // Hide loading spinner
                const card = canvas.parentElement;
                const spinner = card.querySelector('.avatar-loading');
                if (spinner) {
                    spinner.remove();
                }
                
                const model = gltf.scene;
                model.position.set(0, 0, 0);
                model.scale.set(1, 1, 1);
                
                // Enable shadows
                model.traverse((node) => {
                    if (node.isMesh) {
                        node.castShadow = true;
                        node.receiveShadow = true;
                    }
                });

                scene.add(model);
                this.avatars[index] = { model, mixer: null, clock: new THREE.Clock() };

                // Setup animations if available
                if (gltf.animations && gltf.animations.length > 0) {
                    const mixer = new THREE.AnimationMixer(model);
                    const action = mixer.clipAction(gltf.animations[0]);
                    action.play();
                    this.avatars[index].mixer = mixer;
                }
                
                // Force initial render
                renderer.render(scene, camera);
            },
            (progress) => {
                console.log('Loading progress:', avatarFile, progress);
            },
            (error) => {
                console.error('Error loading avatar:', avatarFile, error);
            }
        );

        // Store references
        this.scenes[index] = scene;
        this.cameras[index] = camera;
        this.renderers[index] = renderer;
    }

    setupEventListeners() {
        const cards = this.container.querySelectorAll('.avatar-card');
        
        cards.forEach((card) => {
            // Click to select
            card.addEventListener('click', () => {
                this.selectAvatar(card);
            });

            // Hover effects
            card.addEventListener('mouseenter', () => {
                if (!card.classList.contains('selected')) {
                    card.style.transform = 'translateY(-8px)';
                    card.style.boxShadow = '0 20px 40px rgba(99, 102, 241, 0.3)';
                    card.style.borderColor = '#6366f1';
                }
            });

            card.addEventListener('mouseleave', () => {
                if (!card.classList.contains('selected')) {
                    card.style.transform = '';
                    card.style.boxShadow = '0 1px 3px rgba(0, 0, 0, 0.1)';
                    card.style.borderColor = '#e2e8f0';
                }
            });
        });
    }

    selectAvatar(card) {
        // Remove previous selection
        const previousSelected = this.container.querySelector('.avatar-card.selected');
        if (previousSelected) {
            previousSelected.classList.remove('selected');
            previousSelected.style.border = '3px solid #e2e8f0';
            previousSelected.style.transform = '';
            previousSelected.style.boxShadow = '0 1px 3px rgba(0, 0, 0, 0.1)';
            previousSelected.style.background = 'white';
            const prevCheckmark = previousSelected.querySelector('.avatar-checkmark');
            if (prevCheckmark) {
                prevCheckmark.style.display = 'none';
            }
        }

        // Add new selection with enhanced styling
        card.classList.add('selected');
        card.style.border = '3px solid #6366f1';
        card.style.transform = 'scale(1.02)';
        card.style.boxShadow = '0 20px 50px rgba(99, 102, 241, 0.4)';
        card.style.background = 'linear-gradient(135deg, #f0f4ff 0%, #ffffff 100%)';
        
        // Show checkmark with animation
        const checkmark = card.querySelector('.avatar-checkmark');
        if (checkmark) {
            checkmark.style.display = 'flex';
            checkmark.style.animation = 'checkmarkPop 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
        }

        // Animate selection (jump effect)
        card.style.animation = 'avatarJump 0.7s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
        setTimeout(() => {
            card.style.animation = '';
        }, 700);

        // Store selected avatar
        this.selectedAvatar = card.dataset.file;

        // Update hidden input if exists
        const hiddenInput = document.getElementById('selected-avatar');
        if (hiddenInput) {
            hiddenInput.value = this.selectedAvatar;
        }

        // Dispatch custom event
        const event = new CustomEvent('avatarSelected', {
            detail: { avatarFile: this.selectedAvatar }
        });
        document.dispatchEvent(event);
    }

    startAnimationLoop() {
        console.log('Starting animation loop for', this.renderers.length, 'avatars');
        
        const animate = () => {
            requestAnimationFrame(animate);

            // Render each avatar scene
            this.renderers.forEach((renderer, index) => {
                if (this.avatars[index] && this.avatars[index].model) {
                    // Gentle rotation
                    this.avatars[index].model.rotation.y += 0.005;

                    // Subtle bobbing animation
                    const time = Date.now() * 0.001;
                    this.avatars[index].model.position.y = Math.sin(time + index) * 0.05;

                    // Update animations
                    if (this.avatars[index].mixer) {
                        const delta = this.avatars[index].clock.getDelta();
                        this.avatars[index].mixer.update(delta);
                    }
                }

                // Always render even if model not loaded yet
                if (this.scenes[index] && this.cameras[index] && renderer) {
                    renderer.render(this.scenes[index], this.cameras[index]);
                }
            });
        };

        animate();
    }

    getSelectedAvatar() {
        return this.selectedAvatar;
    }
}

// Add animations and styles
const style = document.createElement('style');
style.textContent = `
    @keyframes avatarJump {
        0%, 100% {
            transform: scale(1.02) translateY(0);
        }
        50% {
            transform: scale(1.05) translateY(-12px);
        }
    }
    
    @keyframes checkmarkPop {
        0% {
            transform: scale(0);
            opacity: 0;
        }
        50% {
            transform: scale(1.2);
        }
        100% {
            transform: scale(1);
            opacity: 1;
        }
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    
    .spinner {
        width: 40px;
        height: 40px;
        border: 4px solid rgba(255, 255, 255, 0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    /* Smooth transitions for avatar cards */
    .avatar-card {
        will-change: transform;
    }
    
    .avatar-card canvas {
        transition: transform 0.3s ease;
    }
    
    .avatar-card:hover canvas {
        transform: scale(1.05);
    }
`;
document.head.appendChild(style);

// Initialize function that can be called externally
window.initAvatarGallery = function() {
    console.log('Initializing avatar gallery...');
    if (document.getElementById('avatar-gallery-container') && !window.avatarSelector) {
        window.avatarSelector = new AvatarSelector('avatar-gallery-container');
        console.log('Avatar gallery initialized successfully');
    }
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        // Don't auto-init, wait for step 2 to be visible
        console.log('DOM ready, waiting for avatar gallery container...');
    });
} else {
    console.log('Document already loaded');
}
