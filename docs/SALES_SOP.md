# Sales SOP: lead to delivered project

How a customer moves through the SALES section of the admin, and which
screen owns each step.

## Names (one name per idea)

| Name | Meaning | Screen | Table |
|---|---|---|---|
| **Lead** | An enquiry we are trying to win | Sales → Leads | `tbl_leads` |
| **Client** | The organisation or person we deal with | Sales → Clients | `tbl_clients` |
| **Client Project** | One product delivered to one client (URL, package, period) | Sales → Client Projects | `tbl_client_projects` |
| **Sales Document** | Quotation, invoice, contract… | Sales → Sales Documents | `tbl_documents` |
| **Product** | Something we sell (ERP, POS, Website…) | Sales → Products | `tbl_projects` |
| **Portfolio** | Work shown on the public website (marketing only) | Website CMS → Portfolio | `tbl_cms_projects` |

A Client is a **Customer** once it has at least one won Client Project.
Until then it is a **Prospect**.

## Sidebar

```
SALES
  Sales             Leads · Clients · Client Projects · Sales Documents · Products
MARKETING
  Website CMS       (… Portfolio …)
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

## Notes

- Old `module=leads` / `module=clients` links (bookmarks, emails) redirect
  to the Sales module automatically. Old quotation links open the same
  quotation in Sales Documents.
- Quotations that clashed with an existing number were renumbered during the
  move (e.g. `QTN-2026-0001` → `QTN-2026-0002`). The original number is kept
  in `tbl_quotations.quotation_number` for reference.
