export const DOCUMENT_MAX_SIZE_MB = 50;

export const DOCUMENT_ALLOWED_EXTENSIONS = [
    'step',
    'stp',
    'iges',
    'igs',
    'dwg',
    'dxf',
    'sldprt',
    'stl',
    '3mf',
    'pdf',
    'doc',
    'docx',
    'xls',
    'xlsx',
    'csv',
    'png',
    'jpg',
    'jpeg',
    'tif',
    'tiff',
] as const;

export const DOCUMENT_ACCEPT_EXTENSIONS = DOCUMENT_ALLOWED_EXTENSIONS.map(
    (extension) => (extension.startsWith('.') ? extension : `.${extension}`),
);

export const DOCUMENT_INPUT_ACCEPT = DOCUMENT_ACCEPT_EXTENSIONS.join(',');

export const DOCUMENT_ACCEPT_LABEL =
    'CAD, PDF, Office, and image files (50 MB max).';
