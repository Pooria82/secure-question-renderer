# Secure Question Renderer

An enterprise-grade, highly optimized, and scalable backend solution for converting raw question banks (JSON or Word documents) into aggressively protected, anti-OCR PDF files. This project is purpose-built to defend the intellectual property of sensitive examinations.

## 🚀 Quick Start (Docker Environment)

We provide a production-ready, fully containerized Docker environment equipped with PHP 8.3, Gotenberg, Ghostscript, Pandoc, Imagick, and all necessary OS-level dependencies. 

```bash
# 1. Clone the repository
git clone https://github.com/Pooria82/secure-question-renderer.git
cd secure-question-renderer

# 2. Start the Docker containers (App, Nginx, Queue Worker, Gotenberg)
docker-compose up -d --build

# 3. Install composer dependencies (inside container)
docker-compose exec app composer install

# 4. Copy environment file and generate key
docker-compose exec app cp .env.example .env
docker-compose exec app php artisan key:generate

# 5. Run database migrations (SQLite is pre-configured)
docker-compose exec app php artisan migrate
```

Your API is now available on `http://localhost:8000`.

---

## 📖 API Documentation

### 1. Convert Document
Submit a JSON or DOCX file to initiate the secure PDF rendering pipeline.

- **Endpoint:** `POST /api/convert`
- **Headers:** `Accept: application/json`
- **Body (multipart/form-data):**
  - `file`: The `.json` or `.docx` file containing the questions.
- **Response (202 Accepted):**
```json
{
    "message": "Secure PDF generation has started.",
    "batch_id": "9b123456-e89b-12d3-a456-426614174000",
    "status_url": "http://localhost:8000/api/download/9b123456-e89b-12d3-a456-426614174000",
    "output_filename": "secure_exam_1734567890.pdf"
}
```

### 2. Check Status & Download
Poll this endpoint using the `batch_id`. Once processing is complete, this endpoint will automatically initiate the PDF file download.

- **Endpoint:** `GET /api/download/{batch_id}?filename={output_filename}`
- **Response (200 OK - Processing):**
```json
{
    "status": "processing",
    "progress": 45
}
```
- **Response (200 OK - Completed):**
  - Returns the `application/pdf` binary stream and permanently deletes the file from the server.

---

## 🛡️ Security Measures (Anti-OCR)

The primary goal of this application is to thwart optical character recognition (OCR) and text extraction techniques. We implement security at two distinct layers:

### 1. Rasterization & Visual Disruption (Intervention/GD)
Each document is first rendered into a standard PDF, and then **flattened** into physical pixel images via Ghostscript. Before saving, Intervention Image applies:
- **Visual Noise & Pixelation**: Subtly disrupts edge-detection algorithms used by OCR AI without impacting human readability.
- **Diagonal Watermarks**: Imposes a semi-transparent, angled text layer across the image body.
- **Interfering Lines**: Randomly generated line artifacts are drawn across the image, breaking the structural layout of the words for programmatic parsers.

### 2. Document Level Assembly
When compiling the final PDF via `Imagick`, the resulting document is essentially a gallery of flattened photographs.
- Text Selection and Copying is physically impossible.
- Programmatic Text Extraction via tools like PyPDF2 will return blank strings.

---

## ⚡ Performance & Scalability

Rendering documents to heavily protected images is extremely resource-intensive. Executing this synchronously would inevitably cause HTTP timeouts.

To ensure enterprise-grade reliability, we utilize **Laravel Job Batching**:
1. **O(1) Bulk Rasterization:** Instead of repeatedly loading memory-heavy processes, `GhostscriptRasterizerService` blasts the intermediate PDF into high-res PNG pages in a single O(1) process.
2. **Parallel Image Processing:** A batch of isolated `RenderSecureVisualPageJob`s are dispatched to the Queue. Background workers process these images concurrently. This allows for **horizontal scaling**—simply spawn more Queue workers to handle higher loads.
3. **Blazing-Fast Native Assembly:** Once the batch completes, `CompileSecurePdfJob` stitches the images into the secure PDF using native C-bound `Imagick` (cutting compilation time by ~70%), and rigorously cleans up all temporary files.

This architecture guarantees a fast HTTP response (`202 Accepted`) and delegates heavy lifting to resilient, scalable background processes.

---

## 🧹 Garbage Collection
The application adheres to the strictest data-minimization practices:
- **Immediate Input Purging:** Uploaded `.json` and `.docx` files are `unlink()`ed milliseconds after they are loaded into memory.
- **Self-Destructing Outputs:** The final PDF deletes itself from the disk the exact moment the client successfully downloads it (`deleteFileAfterSend(true)`).
- **Cron Failsafe:** A scheduled daily Laravel task automatically prunes orphaned temporary directories in the event of a catastrophic server hardware crash.
