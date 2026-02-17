/**
 * Face Capture Component for SkillHarbor
 * Captures and processes face data for authentication
 */

class FaceCapture {
    constructor() {
        this.video = null;
        this.canvas = null;
        this.stream = null;
        this.faceData = null;
        this.isCapturing = false;
        this.capturedImages = [];
        this.requiredCaptures = 3; // Capture 3 angles for better recognition
    }

    /**
     * Initialize camera and video stream
     */
    async initCamera(videoElement) {
        this.video = videoElement;
        
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 1280, min: 640 },
                    height: { ideal: 720, min: 480 },
                    facingMode: 'user',
                    frameRate: { ideal: 30, max: 30 }
                },
                audio: false
            });
            
            this.video.srcObject = this.stream;
            await this.video.play();
            
            return true;
        } catch (error) {
            console.error('Error accessing camera:', error);
            throw new Error('Camera access denied. Please allow camera access to use Face ID.');
        }
    }

    /**
     * Stop camera stream
     */
    stopCamera() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
        if (this.video) {
            this.video.srcObject = null;
        }
    }

    /**
     * Capture current frame from video
     */
    captureFrame() {
        if (!this.video) return null;

        const canvas = document.createElement('canvas');
        
        // Optimize: Use smaller size for faster comparison (640x480 is plenty)
        const targetWidth = 640;
        const targetHeight = 480;
        
        canvas.width = targetWidth;
        canvas.height = targetHeight;
        
        const ctx = canvas.getContext('2d');
        ctx.drawImage(this.video, 0, 0, targetWidth, targetHeight);
        
        // Convert to base64 with moderate quality (75% - balance between quality and size)
        return canvas.toDataURL('image/jpeg', 0.75);
    }

    /**
     * Capture multiple images for face data
     */
    async captureFaceData(onProgress) {
        this.capturedImages = [];
        
        for (let i = 0; i < this.requiredCaptures; i++) {
            // Wait a bit between captures
            if (i > 0) {
                await this.sleep(800);
            }
            
            const image = this.captureFrame();
            if (image) {
                this.capturedImages.push(image);
                if (onProgress) {
                    onProgress((i + 1) / this.requiredCaptures);
                }
            }
        }
        
        // Combine all captures into face data
        this.faceData = {
            images: this.capturedImages,
            timestamp: new Date().toISOString(),
            captureCount: this.capturedImages.length
        };
        
        return this.faceData;
    }

    /**
     * Get face data as JSON string
     */
    getFaceDataJSON() {
        return this.faceData ? JSON.stringify(this.faceData) : null;
    }

    /**
     * Compare captured face with stored face data
     */
    async compareFaces(storedFaceData) {
        if (!this.faceData || !storedFaceData) {
            return { match: false, confidence: 0 };
        }

        try {
            const stored = typeof storedFaceData === 'string' 
                ? JSON.parse(storedFaceData) 
                : storedFaceData;
            
            // Simple image comparison (in production, use proper face recognition)
            // For now, we'll use a basic similarity check
            const currentImage = this.capturedImages[0];
            const storedImage = stored.images[0];
            
            const similarity = await this.compareImages(currentImage, storedImage);
            
            return {
                match: similarity > 0.85, // 85% similarity threshold
                confidence: similarity
            };
        } catch (error) {
            console.error('Error comparing faces:', error);
            return { match: false, confidence: 0 };
        }
    }

    /**
     * Basic image comparison (simplified)
     * In production, use Face-API.js or similar library for proper face recognition
     */
    async compareImages(img1, img2) {
        // This is a placeholder
        // In production, use proper face detection and comparison
        return new Promise((resolve) => {
            // Simulate comparison
            const canvas1 = this.imageToCanvas(img1);
            const canvas2 = this.imageToCanvas(img2);
            
            const data1 = canvas1.getContext('2d').getImageData(0, 0, canvas1.width, canvas1.height);
            const data2 = canvas2.getContext('2d').getImageData(0, 0, canvas2.width, canvas2.height);
            
            let diff = 0;
            const pixels = data1.data.length;
            
            for (let i = 0; i < pixels; i += 4) {
                diff += Math.abs(data1.data[i] - data2.data[i]); // R
                diff += Math.abs(data1.data[i+1] - data2.data[i+1]); // G
                diff += Math.abs(data1.data[i+2] - data2.data[i+2]); // B
            }
            
            const avgDiff = diff / (pixels / 4) / 255;
            const similarity = 1 - (avgDiff / 3);
            
            resolve(Math.max(0, Math.min(1, similarity)));
        });
    }

    /**
     * Convert base64 image to canvas
     */
    imageToCanvas(base64Image) {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const img = new Image();
        
        img.src = base64Image;
        canvas.width = 100; // Resize for comparison
        canvas.height = 100;
        ctx.drawImage(img, 0, 0, 100, 100);
        
        return canvas;
    }

    /**
     * Helper: Sleep function
     */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Check if browser supports required features
     */
    static isSupported() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    }
}

// Export for use in other files
window.FaceCapture = FaceCapture;
