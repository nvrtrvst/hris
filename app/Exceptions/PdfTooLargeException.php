<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Guard jumlah baris untuk render PDF. DomPDF boros memori & lambat —
 * data di atas batas lebih baik diarahkan user ke export Excel.
 */
class PdfTooLargeException extends RuntimeException {}
