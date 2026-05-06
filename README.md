# SignMe - Online PDF Signature System

SignMe is a web application designed for online PDF document signing. It allows users to upload PDF files, specify signers, and track the signing progress through an intuitive dashboard.

## Features

- **Upload PDF:** Easy PDF upload to start the signing workflow.
- **Dashboard Overview:** Track all your documents, their statuses, and signing progress.
- **Multi-Signer Support:** Send documents to multiple people for signing.
- **Audit Trail:** Detailed history of document actions (created, viewed, signed).
- **Secure Signing:** Personalized signing links with optional access codes.
- **Auto-Fill Signatures:** Automatically merge signatures into the PDF upon completion.

## Technology Stack

- **Backend:** PHP
- **Frontend:** HTML, Vanilla CSS, JavaScript
- **Database:** MySQL
- **PDF Processing:** PDF-lib

## Installation

1. Clone the repository:
   ```bash
   git clone [repository-url]
   ```
2. Import `db_init.sql` into your MySQL database.
3. Configure `config.php` with your database credentials and SMTP settings.
4. Ensure the `uploads/` directory is writable.

## License

MIT License
