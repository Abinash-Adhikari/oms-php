<?php
/**
 * SB-Tech — Type-specific form sections for the document engine.
 * Included inside the add/edit form of documents.php.
 *
 * Variables available: $docType, $edit, $typeConfig
 */
$ev = $edit ? $edit : [];
?>

<?php /* ═══════════════════════════════════════════════════════════════
   INVOICE: Payment Terms, Bank Details, Late Fee
   ═══════════════════════════════════════════════════════════════ */ ?>
<?php if ($docType === 'invoice'): ?>
    <div class="card card-outline mt-3">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-credit-card mr-1"></i>Payment Details</h3></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group"><label>Payment Terms</label>
                        <textarea name="payment_terms" class="form-control" rows="3" placeholder="e.g. Net 30 days, 50% advance…"><?= e($ev['payment_terms'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group"><label>Late Fee (% per month)</label>
                        <input type="number" name="late_fee_pct" class="form-control" step="0.01" min="0" max="100" value="<?= e($ev['late_fee_pct'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label>Bank Name</label><input type="text" name="bank_name" class="form-control" value="<?= e($ev['bank_name'] ?? '') ?>"></div>
                    <div class="form-group"><label>Account Number</label><input type="text" name="bank_account" class="form-control" value="<?= e($ev['bank_account'] ?? '') ?>"></div>
                    <div class="form-group"><label>Routing / Swift Code</label><input type="text" name="bank_routing" class="form-control" value="<?= e($ev['bank_routing'] ?? '') ?>"></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php /* ═══════════════════════════════════════════════════════════════
   PROPOSAL: Rich sections — Executive Summary, Problem, Solution,
   Timeline, Team, Case Studies, Why Us
   ═══════════════════════════════════════════════════════════════ */ ?>
<?php if ($docType === 'proposal'): ?>
    <div class="card card-outline mt-3">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-lightbulb mr-1"></i>Proposal Sections</h3></div>
        <div class="card-body">
            <div class="form-group">
                <label class="font-weight-bold">Executive Summary</label>
                <textarea name="exec_summary" class="form-control" rows="4" placeholder="Brief overview of the proposal…"><?= e($ev['exec_summary'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="font-weight-bold">Problem Statement</label>
                <textarea name="problem_statement" class="form-control" rows="4" placeholder="What problem are we solving?"><?= e($ev['problem_statement'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="font-weight-bold">Proposed Solution</label>
                <textarea name="proposed_solution" class="form-control" rows="6" placeholder="How will we solve it?"><?= e($ev['proposed_solution'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="font-weight-bold">Timeline &amp; Milestones</label>
                <textarea name="timeline_text" class="form-control" rows="4" placeholder="Phase 1: Discovery (Week 1-2)…"><?= e($ev['timeline_text'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="font-weight-bold">Team</label>
                <textarea name="team_text" class="form-control" rows="3" placeholder="Project Manager: …&#10;Lead Developer: …"><?= e($ev['team_text'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="font-weight-bold">Case Studies / Past Work</label>
                <textarea name="case_studies" class="form-control" rows="4" placeholder="Similar projects we've delivered…"><?= e($ev['case_studies'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="font-weight-bold">Why Choose Us</label>
                <textarea name="why_us" class="form-control" rows="3" placeholder="Our unique advantages…"><?= e($ev['why_us'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php /* ═══════════════════════════════════════════════════════════════
   CONTRACT: Legal Clauses, Payment Schedule, Signatures
   ═══════════════════════════════════════════════════════════════ */ ?>
<?php if ($docType === 'contract'): ?>
    <div class="card card-outline mt-3">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-file-contract mr-1"></i>Contract Details</h3></div>
        <div class="card-body">
            <div class="form-group">
                <label class="font-weight-bold">Contract Clauses (one per line)</label>
                <textarea name="contract_clauses" class="form-control" rows="8" placeholder="1. Scope of Work: …&#10;2. Payment Terms: …&#10;3. Confidentiality: …&#10;4. Termination: …&#10;5. Liability: …&#10;6. Intellectual Property: …&#10;7. Dispute Resolution: …"><?= e($ev['contract_clauses'] ?? '') ?></textarea>
                <small class="text-muted">Write each clause on a new line. They will be numbered automatically in the print view.</small>
            </div>
            <div class="form-group">
                <label class="font-weight-bold">Payment Schedule (one per line: Milestone — Amount)</label>
                <textarea name="payment_schedule" class="form-control" rows="4" placeholder="Contract signing — 30%&#10;Design approval — 20%&#10;Development complete — 30%&#10;Final delivery — 20%"><?= e($ev['payment_schedule'] ?? '') ?></textarea>
            </div>
            <hr>
            <h6 class="text-muted text-uppercase mb-3" style="font-size:.75rem">Signatures</h6>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group"><label>Party 1 — Name</label><input type="text" name="signature_left_name" class="form-control" value="<?= e($ev['signature_left_name'] ?? '') ?>"></div>
                    <div class="form-group"><label>Title</label><input type="text" name="signature_left_title" class="form-control" value="<?= e($ev['signature_left_title'] ?? '') ?>"></div>
                    <div class="form-group"><label>Signed Date</label><input type="date" name="signature_left_date" class="form-control" value="<?= e($ev['signature_left_date'] ?? '') ?>"></div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label>Party 2 — Name</label><input type="text" name="signature_right_name" class="form-control" value="<?= e($ev['signature_right_name'] ?? '') ?>"></div>
                    <div class="form-group"><label>Title</label><input type="text" name="signature_right_title" class="form-control" value="<?= e($ev['signature_right_title'] ?? '') ?>"></div>
                    <div class="form-group"><label>Signed Date</label><input type="date" name="signature_right_date" class="form-control" value="<?= e($ev['signature_right_date'] ?? '') ?>"></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php /* ═══════════════════════════════════════════════════════════════
   PRICE LIST: Category
   ═══════════════════════════════════════════════════════════════ */ ?>
<?php if ($docType === 'price_list'): ?>
    <div class="card card-outline mt-3">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-tags mr-1"></i>Price List Settings</h3></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group"><label>Category</label>
                        <select name="pl_category" class="form-control">
                            <option value="">— All Categories —</option>
                            <?php foreach (['Web Development', 'Mobile Apps', 'Cloud Services', 'UI/UX Design', 'Data Analytics', 'Consulting', 'Support & Maintenance'] as $cat): ?>
                                <option value="<?= $cat ?>" <?= ($ev['pl_category'] ?? '') === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="alert alert-info py-2 mb-0"><i class="fas fa-info-circle mr-1"></i>Price lists don't require client info. Add your service/product items with prices below.</div>
        </div>
    </div>
<?php endif; ?>

<?php /* ═══════════════════════════════════════════════════════════════
   BROCHURE: Sections (JSON), Hero Image
   ═══════════════════════════════════════════════════════════════ */ ?>
<?php if ($docType === 'brochure'): ?>
    <div class="card card-outline mt-3">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-book-open mr-1"></i>Brochure Content</h3></div>
        <div class="card-body">
            <div class="form-group">
                <label class="font-weight-bold">Hero Section Title</label>
                <input type="text" name="brochure_hero_title" class="form-control" placeholder="e.g. SB-Tech — Your Technology Partner" value="<?= e($ev['subject'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="font-weight-bold">About / Company Story</label>
                <textarea name="brochure_about" class="form-control" rows="4" placeholder="Tell your company story…"><?= e($ev['exec_summary'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="font-weight-bold">Services (one per line: Service Name — Brief Description)</label>
                <textarea name="brochure_services" class="form-control" rows="5" placeholder="Web Development — Custom websites and web applications&#10;Mobile Apps — iOS and Android development&#10;Cloud Services — AWS, Azure, GCP migration and management"><?= e($ev['proposed_solution'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="font-weight-bold">Key Stats / Achievements</label>
                <textarea name="brochure_stats" class="form-control" rows="3" placeholder="150+ Projects Delivered&#10;50+ Happy Clients&#10;5+ Years Experience"><?= e($ev['why_us'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="font-weight-bold">Contact Information</label>
                <textarea name="brochure_contact" class="form-control" rows="3" placeholder="Phone: +977-1-4XXXXXX&#10;Email: info@sbtech.com.np&#10;Address: Kathmandu, Nepal"><?= e($ev['notes'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="font-weight-bold">Call to Action</label>
                <input type="text" name="brochure_cta" class="form-control" placeholder="e.g. Get a Free Consultation Today!" value="<?= e($ev['terms'] ?? '') ?>">
            </div>
        </div>
    </div>
<?php endif; ?>

<?php /* ═══════════════════════════════════════════════════════════════
   CREDIT NOTE: Reference Invoice, Reason
   ═══════════════════════════════════════════════════════════════ */ ?>
<?php if ($docType === 'credit_note'): ?>
    <div class="card card-outline mt-3">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-undo mr-1"></i>Credit Note Details</h3></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Original Invoice #</label>
                        <select name="original_invoice_id" class="form-control" id="origInvoiceSelect">
                            <option value="">— Select invoice —</option>
                            <?php
                            $invoices = $db->select("SELECT id, document_number, client_name, total FROM `tbl_documents` WHERE document_type = 'invoice' ORDER BY added_on DESC");
                            foreach ($invoices as $inv): ?>
                                <option value="<?= (int) $inv['id'] ?>"
                                    data-client="<?= e($inv['client_name'] ?? '') ?>"
                                    data-amount="<?= e(formatMoney($inv['total'])) ?>"
                                    <?= ($ev['original_invoice_id'] ?? 0) == $inv['id'] ? 'selected' : '' ?>>
                                    <?= e($inv['document_number']) ?> — <?= e($inv['client_name']) ?> (NPR <?= e(formatMoney($inv['total'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label>Reason for Credit</label>
                        <textarea name="credit_reason" class="form-control" rows="3" placeholder="e.g. Service not delivered, Overcharged, Return…"><?= e($ev['credit_reason'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <div class="alert alert-warning py-2 mb-0"><i class="fas fa-exclamation-triangle mr-1"></i>Add the items being credited below. The total credit amount will be shown on the document.</div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var sel = document.getElementById('origInvoiceSelect');
        if (sel) sel.addEventListener('change', function() {
            var opt = this.options[this.selectedIndex];
            if (opt && opt.dataset.client) {
                var cnf = document.getElementById('clientNameField');
                if (cnf && !cnf.value) cnf.value = opt.dataset.client;
            }
        });
    });
    </script>
<?php endif; ?>
