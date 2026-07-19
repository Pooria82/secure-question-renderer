# Secure Question Renderer

An enterprise-grade, secure, and scalable backend solution for converting question sets (from JSON or Word documents) into aggressively protected, anti-OCR PDF files. This project is built primarily to defend the intellectual property of sensitive question banks.

## Quick Start (Docker Environment)

We provide a production-ready, fully containerized Docker environment equipped with PHP 8.3, Node.js, Puppeteer, and all the OS-level dependencies required for Headless Chrome rendering. You do not need to install Chrome or Node.js on your host machine.

```bash
# 1. Clone the repository
git clone https://github.com/Pooria82/secure-question-renderer.git
cd secure-question-renderer

# 2. Start the Docker containers (App, Nginx, Queue Worker)
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

## System Architecture

This application strictly enforces **SOLID principles** and utilizes a **Service-Oriented Architecture (SOA)** to keep controllers lean and business logic decoupled, reusable, and heavily tested.

### Strategy Pattern for Input Parsing
To accommodate varying file uploads, we've implemented the **Strategy Pattern** for input handling.
- `QuestionParserInterface` acts as the uniform contract.
- `JsonQuestionParser` and `WordQuestionParser` encapsulate the unique extraction algorithms.
- Adding a new parser (e.g., CSV) requires zero modification to the core `QuestionProcessingService`, abiding by the Open/Closed Principle.

---

## Security Measures (Anti-OCR)

The primary goal of this application is to thwart optical character recognition (OCR) and text extraction techniques. We implement security at two distinct layers:

### 1. Image Level (Intervention Image)
Each extracted question is rendered cleanly via a Laravel Blade view. Using Spatie Browsershot, the HTML is converted to a pixel-perfect image. Before saving, Intervention Image applies:
- **Visual Noise & Pixelation**: Subtly disrupts edge-detection algorithms used by OCR without impacting human readability.
- **Diagonal Watermarks**: Imposes a semi-transparent, angled text layer ("CONFIDENTIAL - SECURE EXAM") across the image body.
- **Interfering Lines**: Randomly generated line artifacts are drawn across the image, breaking the structural layout of the words for AI parsers.

### 2. Document Level (mPDF)
When compiling the final PDF, we enforce strict document-level locking via `mPDF->SetProtection(['print', 'print-highres'])`. This explicitly disables:
- Text Selection and Copying
- Document Modification
- Programmatic Text Extraction

---

## Performance & Scalability

Rendering HTML to Images via Headless Chrome is extremely resource-intensive. Executing this synchronously would inevitably cause API timeouts.

To ensure enterprise-grade reliability, we utilize **Laravel Job Batching**:
1. Upon upload, the file is parsed and a batch of isolated `RenderSecureQuestionImageJob`s are dispatched to the Queue.
2. Background workers process these images concurrently. This allows for **horizontal scaling**—simply spawn more Queue workers to handle higher loads.
3. Once the batch entirely completes, the `then()` callback executes a final `CompileSecurePdfJob` to stitch the images into the secure PDF and rigorously clean up the temporary directory.

This architecture guarantees a fast HTTP response (`202 Accepted`) and delegates heavy lifting to resilient, scalable background processes.
