/**
 * Simple and Reliable Face Recognition
 * Uses perceptual hashing for consistent face matching
 */

class FaceRecognition {
    constructor() {
        this.video = null;
        this.stream = null;
        this.descriptors = [];
    }

    /**
     * Initialize camera
     */
    async initCamera(videoElement) {
        this.video = videoElement;
        
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                    facingMode: 'user'
                },
                audio: false
            });
            
            this.video.srcObject = this.stream;
            await this.video.play();
            return true;
        } catch (error) {
            console.error('Camera error:', error);
            throw new Error('Camera access denied');
        }
    }

    /**
     * Stop camera
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
     * Capture and create face descriptor
     */
    async captureFaceDescriptors(numCaptures = 3, onProgress = null) {
        this.descriptors = [];
        
        for (let i = 0; i < numCaptures; i++) {
            if (i > 0) {
                await this.sleep(500);
            }
            
            const descriptor = await this.createFaceDescriptor();
            if (descriptor) {
                this.descriptors.push(descriptor);
                if (onProgress) {
                    onProgress((i + 1) / numCaptures);
                }
            }
        }
        
        return {
            descriptors: this.descriptors,
            timestamp: new Date().toISOString(),
            count: this.descriptors.length
        };
    }

    /**
     * Create a face descriptor (perceptual hash)
     */
    async createFaceDescriptor() {
        if (!this.video) return null;

        // Capture frame
        const canvas = document.createElement('canvas');
        const size = 32; // Small size for fast processing
        canvas.width = size;
        canvas.height = size;
        
        const ctx = canvas.getContext('2d');
        ctx.drawImage(this.video, 0, 0, size, size);
        
        // Get image data
        const imageData = ctx.getImageData(0, 0, size, size);
        const pixels = imageData.data;
        
        // Create descriptor using average hash
        const descriptor = {
            hash: this.computeAverageHash(pixels, size),
            grayscale: this.computeGrayscaleSignature(pixels, size),
            edges: this.computeEdgeSignature(pixels, size)
        };
        
        return descriptor;
    }

    /**
     * Compute average hash (perceptual hash)
     */
    computeAverageHash(pixels, size) {
        // Convert to grayscale and compute average
        let sum = 0;
        const values = [];
        
        for (let i = 0; i < pixels.length; i += 4) {
            const gray = (pixels[i] + pixels[i + 1] + pixels[i + 2]) / 3;
            values.push(gray);
            sum += gray;
        }
        
        const avg = sum / values.length;
        
        // Create hash based on average
        let hash = '';
        for (let i = 0; i < values.length; i++) {
            hash += values[i] >= avg ? '1' : '0';
        }
        
        return hash;
    }

    /**
     * Compute grayscale signature
     */
    computeGrayscaleSignature(pixels, size) {
        const signature = [];
        const blockSize = 4; // 4x4 blocks
        const blocks = size / blockSize;
        
        for (let by = 0; by < blocks; by++) {
            for (let bx = 0; bx < blocks; bx++) {
                let sum = 0;
                let count = 0;
                
                for (let y = 0; y < blockSize; y++) {
                    for (let x = 0; x < blockSize; x++) {
                        const px = bx * blockSize + x;
                        const py = by * blockSize + y;
                        const i = (py * size + px) * 4;
                        
                        const gray = (pixels[i] + pixels[i + 1] + pixels[i + 2]) / 3;
                        sum += gray;
                        count++;
                    }
                }
                
                signature.push(Math.round(sum / count));
            }
        }
        
        return signature;
    }

    /**
     * Compute edge signature
     */
    computeEdgeSignature(pixels, size) {
        const edges = [];
        
        for (let y = 1; y < size - 1; y++) {
            for (let x = 1; x < size - 1; x++) {
                const i = (y * size + x) * 4;
                const current = (pixels[i] + pixels[i + 1] + pixels[i + 2]) / 3;
                
                const right = ((pixels[i + 4] + pixels[i + 5] + pixels[i + 6]) / 3);
                const bottom = ((pixels[i + size * 4] + pixels[i + size * 4 + 1] + pixels[i + size * 4 + 2]) / 3);
                
                const edgeH = Math.abs(current - right);
                const edgeV = Math.abs(current - bottom);
                const edge = edgeH + edgeV;
                
                if (edge > 30) edges.push(1);
                else edges.push(0);
            }
        }
        
        return edges.join('');
    }

    /**
     * Compare two descriptors
     */
    compareDescriptors(desc1, desc2) {
        if (!desc1 || !desc2) return 0;

        // Compare hashes (Hamming distance)
        const hashSim = this.hammingSimilarity(desc1.hash, desc2.hash);
        
        // Compare grayscale signatures
        const graySim = this.arrayRMSE(desc1.grayscale, desc2.grayscale);
        
        // Compare edges
        const edgeSim = this.hammingSimilarity(desc1.edges, desc2.edges);
        
        // Weighted combination
        const similarity = (hashSim * 0.4) + (graySim * 0.35) + (edgeSim * 0.25);
        
        return similarity;
    }

    /**
     * Hamming similarity (percentage of matching bits)
     */
    hammingSimilarity(str1, str2) {
        if (!str1 || !str2 || str1.length !== str2.length) return 0;
        
        let matches = 0;
        for (let i = 0; i < str1.length; i++) {
            if (str1[i] === str2[i]) matches++;
        }
        
        return matches / str1.length;
    }

    /**
     * Array similarity using RMSE (Root Mean Square Error)
     */
    arrayRMSE(arr1, arr2) {
        if (!arr1 || !arr2 || arr1.length !== arr2.length) return 0;
        
        let sumSquaredDiff = 0;
        for (let i = 0; i < arr1.length; i++) {
            const diff = arr1[i] - arr2[i];
            sumSquaredDiff += diff * diff;
        }
        
        const rmse = Math.sqrt(sumSquaredDiff / arr1.length);
        
        // Convert RMSE to similarity (0-1)
        // Lower RMSE = higher similarity
        const maxRMSE = 255; // Max possible for grayscale
        const similarity = 1 - Math.min(rmse / maxRMSE, 1);
        
        return similarity;
    }

    /**
     * Match face descriptors against stored descriptors
     */
    matchFace(capturedData, storedData) {
        if (!capturedData || !storedData) {
            return { match: false, similarity: 0 };
        }

        const captured = typeof capturedData === 'string' ? 
            JSON.parse(capturedData) : capturedData;
        const stored = typeof storedData === 'string' ? 
            JSON.parse(storedData) : storedData;

        if (!captured.descriptors || !stored.descriptors) {
            return { match: false, similarity: 0 };
        }

        let maxSimilarity = 0;

        // Compare each captured descriptor with each stored descriptor
        for (const captDesc of captured.descriptors) {
            for (const storedDesc of stored.descriptors) {
                const sim = this.compareDescriptors(captDesc, storedDesc);
                if (sim > maxSimilarity) {
                    maxSimilarity = sim;
                }
            }
        }

        // Threshold: 70% similarity = match
        const match = maxSimilarity >= 0.70;

        return {
            match: match,
            similarity: maxSimilarity
        };
    }

    /**
     * Get face data as JSON
     */
    getFaceDataJSON() {
        return JSON.stringify({
            descriptors: this.descriptors,
            timestamp: new Date().toISOString(),
            count: this.descriptors.length
        });
    }

    /**
     * Helper: Sleep
     */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Check browser support
     */
    static isSupported() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    }
}

// Export
window.FaceRecognition = FaceRecognition;
