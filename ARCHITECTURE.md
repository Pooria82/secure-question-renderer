# Architecture Guidelines

## Service-Oriented Architecture (SOA)
This project adheres to a Service-Oriented Architecture (SOA). We encapsulate core business logic within dedicated services, keeping our controllers thin and ensuring our code is reusable, testable, and maintainable.
- `SecurePdfGenerationService`: Orchestrates the overall pipeline and batch dispatching.
- `DocumentConverterService`: Wraps Pandoc conversion of DOCX to HTML.
- `GhostscriptRasterizerService`: Executes O(1) bulk rasterization of PDF pages to physical images.
- `HtmlSanitizerService`: Manipulates HTML DOM to sanitize unwanted tracking tokens or dangerous tags before PDF rendering.
- `GotenbergClientService`: Handles HTTP communication with the Gotenberg container.

## Strategy & Factory Pattern
We employ the Strategy Pattern (`QuestionParserInterface`) handled by a `ParserFactory` to accommodate different input types (JSON and Word documents). This allows the application to cleanly define a family of input-processing algorithms, encapsulate each one, and make them interchangeable without modifying the controller or services that use them.

## Data Transfer Objects (DTO)
We use `QuestionData` DTOs to enforce standard properties (ID, text, options) across different input sources, ensuring our job processors receive strongly typed, validated data.

## Asynchronous Processing
To ensure high performance and prevent HTTP timeouts during heavy operations, we utilize **Laravel Queues (Database driver) via Laravel Batches**. The resource-intensive Image manipulation process is dispatched to the background, and dynamically scaled by queue workers.

## Technology Stack
- **Gotenberg (Chromium)**: For converting dynamic HTML into a primary layout PDF.
- **Ghostscript**: For bulk-rasterizing the primary PDF into unselectable, flattened physical images.
- **Intervention Image**: For anti-OCR watermarking, noise generation, and pixelation on the raw images.
- **Imagick (Native PHP)**: For ultra-fast, memory-efficient stitching of the flattened images back into the final secure PDF payload.
- **Docker**: A fully containerized environment equipped with Gotenberg, Ghostscript, Pandoc, and Nginx.
