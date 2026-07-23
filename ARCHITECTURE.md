# Architecture Guidelines

## Service-Oriented Architecture (SOA)
This project adheres to a Service-Oriented Architecture (SOA). We encapsulate core business logic within dedicated services, keeping our controllers thin and ensuring our code is reusable, testable, and maintainable.
- `SecurePdfGenerationService`: Orchestrates the overall pipeline and batch dispatching.
- `DocumentConverterService`: Wraps Pandoc conversion of DOCX to HTML.
- `HtmlSanitizerService`: Manipulates HTML DOM using Regex to apply RTL and isolate MathML.
- `GotenbergClientService`: Handles HTTP communication with the Gotenberg container.
- `PdfPageCounterService`: Abstracts away the logic for counting PDF pages (Imagick / Regex).

## Strategy & Factory Pattern
We employ the Strategy Pattern (`QuestionParserInterface`) handled by a `ParserFactory` to accommodate different input types (JSON and Word documents). This allows the application to cleanly define a family of input-processing algorithms, encapsulate each one, and make them interchangeable without modifying the controller or services that use them.

## Data Transfer Objects (DTO)
We use `QuestionData` DTOs to enforce standard properties (ID, text, options) across different input sources, ensuring our job processors receive strongly typed, validated data.

## Asynchronous Processing
To ensure high performance and prevent HTTP timeouts during heavy operations, we utilize **Laravel Queues (Database driver)**. The resource-intensive HTML-to-Image rendering process is dispatched to the background.

## Security & Anti-OCR Layers
We utilize the `intervention/image` package to apply security watermarks and noise to the rendered output. This acts as an anti-OCR layer to protect the integrity and confidentiality of the generated images.

## Technology Stack
- **Browsershot (Puppeteer/Headless Chrome)**: For high-quality, pixel-perfect HTML to Image conversion.
- **Intervention Image**: For watermarking and image manipulation.
- **Gotenberg / Pandoc**: For Word to PDF conversion.
- **mPDF**: For generating the final secured PDF compilation.
- **Docker**: A fully containerized environment equipped with Node.js, Puppeteer, and all required OS-level dependencies for headless Chrome.
