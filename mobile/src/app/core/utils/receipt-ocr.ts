import { createWorker } from 'tesseract.js';

export interface ParsedReceipt {
  rawText: string;
  amount: number | null;
  date: string | null; // ISO yyyy-mm-dd
  merchant: string | null;
}

/**
 * Client-side OCR via Tesseract.js — no external API, no account/key, consistent with every
 * other integration this app uses. Trade-off: the language data (~a few MB) is fetched from
 * Tesseract's CDN on first use and cached by the browser afterward, so scanning a receipt
 * needs a network connection at least once (unlike the rest of the offline-first expense flow).
 */
export async function scanReceipt(image: File | Blob | string): Promise<ParsedReceipt> {
  const worker = await createWorker('fra');
  try {
    const { data: { text } } = await worker.recognize(image);
    return parseReceiptText(text);
  } finally {
    await worker.terminate();
  }
}

export function parseReceiptText(text: string): ParsedReceipt {
  const lines = text.split('\n').map(l => l.trim()).filter(Boolean);

  return {
    rawText: text,
    amount: extractAmount(text),
    date: extractDate(text),
    merchant: lines[0] ?? null,
  };
}

const TOTAL_KEYWORDS = /total|montant|à payer|a payer|net à payer|net a payer/i;
const AMOUNT_PATTERN = /(\d{1,4}[.,]\d{2})\b/;

function extractAmount(text: string): number | null {
  for (const line of text.split('\n')) {
    if (TOTAL_KEYWORDS.test(line)) {
      const match = line.match(AMOUNT_PATTERN);
      if (match) return parseFloat(match[1].replace(',', '.'));
    }
  }

  // No "total" line recognized — fall back to the largest decimal amount anywhere in the
  // receipt (in practice the grand total is usually the biggest number on the page).
  const globalPattern = new RegExp(AMOUNT_PATTERN, 'g');
  const amounts: number[] = [];
  let match: RegExpExecArray | null;
  while ((match = globalPattern.exec(text)) !== null) {
    amounts.push(parseFloat(match[1].replace(',', '.')));
  }

  return amounts.length ? Math.max(...amounts) : null;
}

function extractDate(text: string): string | null {
  const dmy = text.match(/\b(\d{2})[/\-.](\d{2})[/\-.](\d{4})\b/);
  if (dmy) return `${dmy[3]}-${dmy[2]}-${dmy[1]}`;

  const ymd = text.match(/\b(\d{4})-(\d{2})-(\d{2})\b/);
  if (ymd) return ymd[0];

  return null;
}
