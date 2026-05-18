<?php
/**
 * PDF Invoice Generator
 * Pure PHP, no external library required
 * Generates clean invoice PDF using HTML→PDF via browser print or server-side HTML export
 *
 * For full PDF generation, this outputs a print-ready HTML page.
 * To use a real PDF library, replace generate() with TCPDF/FPDF/DomPDF calls.
 */

class InvoicePDF {

    public static function generate(int $inv_id): void {
        $inv = DB::row(
            "SELECT i.*, c.first_name, c.last_name, c.email, c.phone, c.company, c.address1, c.city, c.state, c.country
             FROM invoices i JOIN clients c ON c.id = i.client_id WHERE i.id = ?",
            'i', [$inv_id]
        );
        if (!$inv) { http_response_code(404); die('Invoice not found.'); }

        $items   = DB::rows("SELECT * FROM invoice_items WHERE invoice_id = ?", 'i', [$inv_id]);
        $company = DB::setting('company_name', 'Billing Portal');
        $addr    = DB::setting('company_address', '');
        $email   = DB::setting('company_email', '');
        $phone   = DB::setting('company_phone', '');
        $tax_name= DB::setting('tax_name', 'VAT');
        $cur     = $inv['currency'];

        // Set headers for PDF-like download (HTML print version)
        header('Content-Type: text/html; charset=UTF-8');

        echo self::renderHTML($inv, $items, compact('company','addr','email','phone','tax_name','cur'));
    }

    private static function renderHTML(array $inv, array $items, array $meta): string {
        $status_colors = ['paid'=>'#10b981','unpaid'=>'#f59e0b','overdue'=>'#ef4444','cancelled'=>'#6b7280'];
        $sc = $status_colors[$inv['status']] ?? '#6b7280';
        $items_html = '';
        foreach ($items as $item) {
            $items_html .= '<tr>
                <td style="padding:12px 16px;border-bottom:1px solid #f1f5f9;font-size:13px">' . htmlspecialchars($item['description']) . '</td>
                <td style="padding:12px 16px;border-bottom:1px solid #f1f5f9;text-align:center;font-size:13px">' . $item['quantity'] . '</td>
                <td style="padding:12px 16px;border-bottom:1px solid #f1f5f9;text-align:right;font-size:13px">' . format_currency($item['unit_price'], $meta['cur']) . '</td>
                <td style="padding:12px 16px;border-bottom:1px solid #f1f5f9;text-align:right;font-size:13px;font-weight:600">' . format_currency($item['total'], $meta['cur']) . '</td>
            </tr>';
        }

        $client_name = trim(($inv['first_name'] ?? '') . ' ' . ($inv['last_name'] ?? ''));
        $paid_stamp  = $inv['status'] === 'paid' ? '<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-20deg);font-size:60px;font-weight:900;color:rgba(16,185,129,.15);white-space:nowrap;pointer-events:none">PAID</div>' : '';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>Invoice #' . htmlspecialchars($inv['invoice_number']) . '</title>
<style>
@media print {
  .no-print { display: none !important; }
  body { margin: 0; }
  .invoice-wrap { box-shadow: none !important; border: none !important; }
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f1f5f9; padding: 32px; color: #0f172a; }
.invoice-wrap { max-width: 800px; margin: 0 auto; background: #fff; border-radius: 16px; box-shadow: 0 4px 40px rgba(0,0,0,.1); overflow: hidden; position: relative; }
.inv-header { background: linear-gradient(135deg, #0f172a, #1e3a5f); padding: 40px 48px; display: flex; justify-content: space-between; align-items: flex-start; }
.inv-company h1 { color: #fff; font-size: 24px; font-weight: 800; margin-bottom: 8px; }
.inv-company p { color: rgba(255,255,255,.6); font-size: 13px; line-height: 1.6; }
.inv-badge { background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2); border-radius: 12px; padding: 20px 24px; text-align: right; }
.inv-badge .inv-label { color: rgba(255,255,255,.5); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
.inv-badge .inv-number { color: #fff; font-size: 22px; font-weight: 800; margin-top: 4px; }
.inv-badge .inv-status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; margin-top: 8px; background: ' . $sc . '; color: #fff; }
.inv-body { padding: 40px 48px; }
.inv-parties { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 36px; }
.inv-section-label { font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #94a3b8; margin-bottom: 10px; }
.inv-party-name { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
.inv-party-detail { font-size: 13px; color: #64748b; line-height: 1.7; }
.inv-dates { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; background: #f8fafc; border-radius: 12px; padding: 20px 24px; margin-bottom: 32px; }
.inv-date-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #94a3b8; margin-bottom: 4px; }
.inv-date-val { font-size: 14px; font-weight: 600; color: #0f172a; }
table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
thead th { background: #f8fafc; padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; }
thead th:last-child, thead th:nth-child(3), thead th:nth-child(2) { text-align: right; }
thead th:nth-child(2) { text-align: center; }
.inv-totals { display: flex; justify-content: flex-end; margin-bottom: 32px; }
.inv-totals-box { width: 280px; }
.inv-total-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 13px; color: #374151; border-bottom: 1px solid #f1f5f9; }
.inv-total-final { display: flex; justify-content: space-between; padding: 14px 0; font-size: 17px; font-weight: 800; color: #0f172a; }
.inv-notes { background: #f8fafc; border-radius: 10px; padding: 16px 20px; font-size: 13px; color: #64748b; margin-bottom: 32px; }
.inv-footer { border-top: 1px solid #f1f5f9; padding-top: 24px; text-align: center; font-size: 12px; color: #94a3b8; }
.btn-print { background: #0f172a; color: #fff; border: none; padding: 11px 24px; border-radius: 9px; font-size: 14px; font-weight: 600; cursor: pointer; margin-bottom: 24px; }
</style></head><body>
<div class="no-print" style="max-width:800px;margin:0 auto 16px;display:flex;gap:8px">
  <button class="btn-print" onclick="window.print()">🖨 Print / Save PDF</button>
  <a href="javascript:history.back()" style="background:#fff;border:1.5px solid #e2e8f0;color:#0f172a;padding:11px 20px;border-radius:9px;font-size:14px;font-weight:600;text-decoration:none">← Back</a>
</div>
<div class="invoice-wrap">
' . $paid_stamp . '
<div class="inv-header">
  <div class="inv-company">
    <h1>' . htmlspecialchars($meta['company']) . '</h1>
    <p>' . nl2br(htmlspecialchars($meta['addr'])) . '<br>' . htmlspecialchars($meta['email']) . '<br>' . htmlspecialchars($meta['phone']) . '</p>
  </div>
  <div class="inv-badge">
    <div class="inv-label">Invoice</div>
    <div class="inv-number">#' . htmlspecialchars($inv['invoice_number']) . '</div>
    <div class="inv-status">' . strtoupper($inv['status']) . '</div>
  </div>
</div>
<div class="inv-body">
  <div class="inv-parties">
    <div>
      <div class="inv-section-label">From</div>
      <div class="inv-party-name">' . htmlspecialchars($meta['company']) . '</div>
      <div class="inv-party-detail">' . nl2br(htmlspecialchars($meta['addr'])) . '</div>
    </div>
    <div>
      <div class="inv-section-label">Bill To</div>
      <div class="inv-party-name">' . htmlspecialchars($client_name) . '</div>
      <div class="inv-party-detail">' . htmlspecialchars($inv['email']) . '<br>' . htmlspecialchars($inv['company'] ?? '') . '<br>' . htmlspecialchars($inv['address1'] ?? '') . '</div>
    </div>
  </div>
  <div class="inv-dates">
    <div><div class="inv-date-label">Issue Date</div><div class="inv-date-val">' . format_date($inv['created_at']) . '</div></div>
    <div><div class="inv-date-label">Due Date</div><div class="inv-date-val">' . format_date($inv['due_date']) . '</div></div>
    <div><div class="inv-date-label">Paid Date</div><div class="inv-date-val">' . ($inv['paid_date'] ? format_date($inv['paid_date']) : '—') . '</div></div>
  </div>
  <table>
    <thead><tr><th>Description</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
    <tbody>' . $items_html . '</tbody>
  </table>
  <div class="inv-totals">
    <div class="inv-totals-box">
      <div class="inv-total-row"><span>Subtotal</span><span>' . format_currency($inv['subtotal'], $meta['cur']) . '</span></div>
      ' . ($inv['tax_amount'] > 0 ? '<div class="inv-total-row"><span>' . htmlspecialchars($meta['tax_name']) . '</span><span>' . format_currency($inv['tax_amount'], $meta['cur']) . '</span></div>' : '') . '
      ' . ($inv['discount_amount'] > 0 ? '<div class="inv-total-row"><span>Discount</span><span>-' . format_currency($inv['discount_amount'], $meta['cur']) . '</span></div>' : '') . '
      <div class="inv-total-final"><span>Total Due</span><span>' . format_currency($inv['total'], $meta['cur']) . '</span></div>
    </div>
  </div>
  ' . ($inv['notes'] ? '<div class="inv-notes"><strong>Notes:</strong> ' . nl2br(htmlspecialchars($inv['notes'])) . '</div>' : '') . '
  <div class="inv-footer">Thank you for your business! · ' . htmlspecialchars($meta['company']) . '</div>
</div>
</div>
</body></html>';
    }
}
