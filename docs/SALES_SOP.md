# Sales SOP: lead to delivered project

How a customer moves through the SALES section of the admin, and which
screen owns each step.

## Names (one name per idea)

| Name | Meaning | Screen | Table |
|---|---|---|---|
| **Lead** | An enquiry we are trying to win | Sales → Leads | `tbl_leads` |
| **Client** | The organisation or person we deal with | Sales → Clients | `tbl_clients` |
| **Client Project** | One product delivered to one client (URL, package, period) | Sales → Client Projects | `tbl_client_projects` |
| **Payment** | Money received from a client, against an invoice or as an advance | Client detail → Payments & Balance | `tbl_client_payments` |
| **Sales Document** | Quotation, invoice, contract… | Sales → Sales Documents | `tbl_documents` |
| **Product** | Something we sell (ERP, POS, Website…) | Sales → Products | `tbl_projects` |
| **Portfolio** | Work shown on the public website (marketing only) | Website CMS → Portfolio | `tbl_cms_projects` |
| **Website Client** | A client shown publicly (logo carousel, /clients page), linkable to Portfolio items. Separate from Sales Clients — add one here only if the client agreed to be showcased | Website CMS → Clients | `tbl_cms_clients` |

A Client is a **Customer** once it has at least one won Client Project.
Until then it is a **Prospect**.

## Sidebar

```
SALES
  Sales             Leads · Clients · Client Projects · Sales Documents · Products
MARKETING
  Website CMS       (… Portfolio · Clients …)
  Communication
```

## Steps

1. **Capture.** A lead arrives from the website form or is added by hand.
   Assign an owner and pick the **Product** they're interested in.
2. **Qualify.** Link the lead to a **Client**, or create one. A quotation
   needs a client, so do this before quoting.
3. **Demo / trial (optional).** If they use the software before buying, click
   **Start Demo / Trial** on the lead. This creates a Client Project marked
   *not won*, with a trial end date (14 days by default).
4. **Proposal.** Click **Create Quotation** on the lead (or **New Quotation**
   on the client). It opens Sales Documents with the client filled in and the
   lead linked.
5. **Won.** Set the lead to Won. The system creates the Client Project, or
   converts the trial into the won project (same record, same URL). Fill in
   the URL, package, start/end dates and value.
6. **Lost.** Set the lead to Lost with a reason. Any running trial is
   cancelled. The client stays as a Prospect for future follow-up.
7. **After the sale.** Renewals and status (Active / On Hold / Completed) are
   managed on the Client Project; invoices in Sales Documents. The Client
   detail page shows everything for that client in one place.
8. **Get paid.** When money arrives, open the client and click **Record
   Payment** (or the green hand icon on the Clients list). Pick the invoice
   it pays, or "Advance / on account" when there is no invoice yet.
   - The invoice turns **Partially Paid** or **Paid** on its own. Deleting a
     payment turns it back to Sent / Overdue.
   - You cannot pay more than is left on an invoice; record the extra as a
     separate advance.
   - Leave **Post to Accounts** ticked to send a pending receipt voucher
     (Dr the bank/cash account, Cr the income account you pick) to
     Accounts → Posting for approval. Untick it only when no fiscal year is
     open yet.
   - A payment whose voucher is already approved can't be deleted here (a
     rejected voucher is removed along with the payment); an
     accountant must un-approve or reverse the voucher first.

## Tracking money owed

- **Outstanding** = issued invoices (anything except Draft / Cancelled) −
  every payment, advances included. A negative balance is an advance held.
- **Overdue** = the unpaid part of invoices whose due date has passed.
- The Clients list shows Invoiced / Received / Outstanding per client, a
  status badge (Overdue · Due · Settled · Advance · No invoices), totals for
  the current filter, and a **Balance** filter ("Has money due", "Overdue", …).
- The client's **Statement** tab lists invoices and payments by date with a
  running balance.
- A client with recorded payments cannot be deleted.

## Notes

- Old `module=leads` / `module=clients` links (bookmarks, emails) redirect
  to the Sales module automatically. Old quotation links open the same
  quotation in Sales Documents.
- Quotations that clashed with an existing number were renumbered during the
  move (e.g. `QTN-2026-0001` → `QTN-2026-0002`). The original number is kept
  in `tbl_quotations.quotation_number` for reference.
