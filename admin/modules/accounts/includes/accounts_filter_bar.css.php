<?php
/**
 * One-line filter toolbar: fields + action buttons on a single row (desktop/tablet).
 * Applies under .ledger-ap / .postings-ap / .alt-ap / .als-ap / .library-ap / .attendance-ap / .academics-ap / .exam-ap / .webcms-ap / .fees-ap.
 */
?>
<style>.ledger-ap .ledger-filter,
    .postings-ap .ledger-filter,
    .alt-ap .ledger-filter,
    .als-ap .ledger-filter,
    .library-ap .ledger-filter,
    .office-ap .ledger-filter,
    .attendance-ap .ledger-filter,
    .academics-ap .ledger-filter,
    .exam-ap .ledger-filter,
    .webcms-ap .ledger-filter,
    .fees-ap .ledger-filter, .cms-ap .ledger-filter{
        display: flex;
        flex-wrap: nowrap;
        align-items: flex-end;
        gap: 0.55rem 0.65rem;
        padding: 0.65rem 0.85rem;
        overflow: visible;
    }.ledger-ap .ledger-filter__body,
    .postings-ap .ledger-filter__body,
    .alt-ap .ledger-filter__body,
    .als-ap .ledger-filter__body,
    .library-ap .ledger-filter__body,
    .office-ap .ledger-filter__body,
    .attendance-ap .ledger-filter__body,
    .academics-ap .ledger-filter__body,
    .exam-ap .ledger-filter__body,
    .webcms-ap .ledger-filter__body,
    .fees-ap .ledger-filter__body, .cms-ap .ledger-filter__body{
        flex: 1 1 auto;
        min-width: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
    }.ledger-ap .ledger-filter__grid,
    .ledger-ap .ledger-filter__grid--with-type,
    .ledger-ap .ledger-filter__grid--single-date,
    .postings-ap .ledger-filter__grid,
    .postings-ap .ledger-filter__grid--with-type,
    .postings-ap .ledger-filter__grid--single-date,
    .alt-ap .ledger-filter__grid,
    .als-ap .ledger-filter__grid,
    .library-ap .ledger-filter__grid,
    .office-ap .ledger-filter__grid,
    .library-ap .ledger-filter__grid--with-type,
    .office-ap .ledger-filter__grid--with-type,
    .library-ap .ledger-filter__grid--single-date,
    .office-ap .ledger-filter__grid--single-date,
    .attendance-ap .ledger-filter__grid,
    .attendance-ap .ledger-filter__grid--with-type,
    .attendance-ap .ledger-filter__grid--single-date,
    .academics-ap .ledger-filter__grid,
    .exam-ap .ledger-filter__grid,
    .webcms-ap .ledger-filter__grid,
    .fees-ap .ledger-filter__grid,
    .academics-ap .ledger-filter__grid--with-type,
    .exam-ap .ledger-filter__grid--with-type,
    .webcms-ap .ledger-filter__grid--with-type,
    .fees-ap .ledger-filter__grid--with-type,
    .academics-ap .ledger-filter__grid--single-date,
    .exam-ap .ledger-filter__grid--single-date,
    .webcms-ap .ledger-filter__grid--single-date,
    .fees-ap .ledger-filter__grid--single-date, .cms-ap .ledger-filter__grid--single-date{
        display: flex !important;
        flex-wrap: nowrap;
        align-items: flex-end;
        gap: 0.45rem 0.55rem;
        width: 100%;
        grid-template-columns: none !important;
    }.ledger-ap .ledger-filter__field,
    .postings-ap .ledger-filter__field,
    .alt-ap .ledger-filter__field,
    .als-ap .ledger-filter__field,
    .library-ap .ledger-filter__field,
    .office-ap .ledger-filter__field,
    .attendance-ap .ledger-filter__field,
    .academics-ap .ledger-filter__field,
    .exam-ap .ledger-filter__field,
    .webcms-ap .ledger-filter__field,
    .fees-ap .ledger-filter__field, .cms-ap .ledger-filter__field{
        flex: 1 1 0;
        min-width: 5rem;
        max-width: 11.5rem;
    }.ledger-ap .ledger-filter__field--kw,
    .postings-ap .ledger-filter__field--kw,
    .alt-ap .ledger-filter__field--kw,
    .als-ap .ledger-filter__field--kw,
    .library-ap .ledger-filter__field--kw,
    .office-ap .ledger-filter__field--kw,
    .attendance-ap .ledger-filter__field--kw,
    .academics-ap .ledger-filter__field--kw,
    .exam-ap .ledger-filter__field--kw,
    .webcms-ap .ledger-filter__field--kw,
    .fees-ap .ledger-filter__field--kw, .cms-ap .ledger-filter__field--kw{
        flex: 1.35 1 0;
        min-width: 6.5rem;
        max-width: 14rem;
    }.ledger-ap .ledger-filter__field--sort,
    .postings-ap .ledger-filter__field--sort,
    .library-ap .ledger-filter__field--sort,
    .office-ap .ledger-filter__field--sort,
    .attendance-ap .ledger-filter__field--sort,
    .academics-ap .ledger-filter__field--sort,
    .exam-ap .ledger-filter__field--sort,
    .webcms-ap .ledger-filter__field--sort,
    .fees-ap .ledger-filter__field--sort, .cms-ap .ledger-filter__field--sort{
        flex: 1.1 1 0;
        min-width: 6rem;
        max-width: 12rem;
    }/* Single AD date-range / wide period field (inline grid-column span still works as wider flex item) */
    .ledger-ap .ledger-filter__field[style*="grid-column"],
    .postings-ap .ledger-filter__field[style*="grid-column"],
    .alt-ap .ledger-filter__field[style*="grid-column"],
    .als-ap .ledger-filter__field[style*="grid-column"],
    .library-ap .ledger-filter__field[style*="grid-column"],
    .office-ap .ledger-filter__field[style*="grid-column"],
    .attendance-ap .ledger-filter__field[style*="grid-column"],
    .academics-ap .ledger-filter__field[style*="grid-column"],
    .exam-ap .ledger-filter__field[style*="grid-column"],
    .webcms-ap .ledger-filter__field[style*="grid-column"],
    .fees-ap .ledger-filter__field[style*="grid-column"], .cms-ap .ledger-filter__field[style*="grid-column"]{
        flex: 1.6 1 0;
        min-width: 8.5rem;
        max-width: 16rem;
    }.ledger-ap .ledger-filter__field--sep,
    .postings-ap .ledger-filter__field--sep,
    .alt-ap .ledger-filter__field--sep,
    .library-ap .ledger-filter__field--sep,
    .office-ap .ledger-filter__field--sep,
    .attendance-ap .ledger-filter__field--sep,
    .academics-ap .ledger-filter__field--sep,
    .exam-ap .ledger-filter__field--sep,
    .webcms-ap .ledger-filter__field--sep,
    .fees-ap .ledger-filter__field--sep, .cms-ap .ledger-filter__field--sep{
        flex: 0 0 auto;
        align-self: flex-end;
        padding-bottom: 0.5rem;
        display: block !important;
    }.ledger-ap .ledger-filter__control,
    .postings-ap .ledger-filter__control,
    .alt-ap .ledger-filter__control,
    .als-ap .ledger-filter__control,
    .library-ap .ledger-filter__control,
    .office-ap .ledger-filter__control,
    .attendance-ap .ledger-filter__control,
    .academics-ap .ledger-filter__control,
    .exam-ap .ledger-filter__control,
    .webcms-ap .ledger-filter__control,
    .fees-ap .ledger-filter__control, .cms-ap .ledger-filter__control{
        height: 2.1rem;
        font-size: 0.8125rem;
        padding: 0.25rem 0.55rem;
    }.ledger-ap .ledger-filter__label,
    .postings-ap .ledger-filter__label,
    .alt-ap .ledger-filter__label,
    .als-ap .ledger-filter__label,
    .library-ap .ledger-filter__label,
    .office-ap .ledger-filter__label,
    .attendance-ap .ledger-filter__label,
    .academics-ap .ledger-filter__label,
    .exam-ap .ledger-filter__label,
    .webcms-ap .ledger-filter__label,
    .fees-ap .ledger-filter__label, .cms-ap .ledger-filter__label{
        font-size: 0.68rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }.ledger-ap .ledger-filter__hint,
    .alt-ap .ledger-filter__hint, .cms-ap .ledger-filter__hint{
        display: none;
    }.ledger-ap .ledger-filter__actions,
    .postings-ap .ledger-filter__actions,
    .alt-ap .ledger-filter__actions,
    .als-ap .ledger-filter__actions,
    .library-ap .ledger-filter__actions,
    .office-ap .ledger-filter__actions,
    .attendance-ap .ledger-filter__actions,
    .academics-ap .ledger-filter__actions,
    .exam-ap .ledger-filter__actions,
    .webcms-ap .ledger-filter__actions,
    .fees-ap .ledger-filter__actions, .cms-ap .ledger-filter__actions{
        flex: 0 0 auto;
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        gap: 0.4rem;
        padding: 0;
        margin: 0;
        border-top: none;
        background: transparent;
        align-self: flex-end;
        padding-bottom: 0;
    }.ledger-ap .ledger-filter__actions-primary,
    .ledger-ap .ledger-filter__actions-export,
    .ledger-ap .ledger-filter__actions-cta,
    .postings-ap .ledger-filter__actions-primary,
    .postings-ap .ledger-filter__actions-cta,
    .alt-ap .ledger-filter__actions-primary,
    .als-ap .ledger-filter__actions-primary,
    .als-ap .ledger-filter__actions-cta,
    .library-ap .ledger-filter__actions-primary,
    .office-ap .ledger-filter__actions-primary,
    .library-ap .ledger-filter__actions-cta,
    .office-ap .ledger-filter__actions-cta,
    .attendance-ap .ledger-filter__actions-primary,
    .attendance-ap .ledger-filter__actions-cta,
    .academics-ap .ledger-filter__actions-primary,
    .exam-ap .ledger-filter__actions-primary,
    .webcms-ap .ledger-filter__actions-primary,
    .fees-ap .ledger-filter__actions-primary,
    .academics-ap .ledger-filter__actions-cta,
    .exam-ap .ledger-filter__actions-cta,
    .webcms-ap .ledger-filter__actions-cta,
    .fees-ap .ledger-filter__actions-cta, .cms-ap .ledger-filter__actions-cta{
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        gap: 0.35rem;
        margin-left: 0 !important;
        width: auto !important;
    }.ledger-ap .ledger-filter__btn,
    .postings-ap .ledger-filter__btn,
    .alt-ap .ledger-filter__btn,
    .als-ap .ledger-filter__btn,
    .library-ap .ledger-filter__btn,
    .office-ap .ledger-filter__btn,
    .attendance-ap .ledger-filter__btn,
    .academics-ap .ledger-filter__btn,
    .exam-ap .ledger-filter__btn,
    .webcms-ap .ledger-filter__btn,
    .fees-ap .ledger-filter__btn, .cms-ap .ledger-filter__btn{
        height: 2.1rem;
        min-height: 2.1rem;
        padding: 0 0.7rem;
        font-size: 0.78rem;
        white-space: nowrap;
        flex: 0 0 auto !important;
        width: auto !important;
    }/* Extra rows under the main filter line (e.g. purchase-book radios) stay full width below */
    .postings-ap .ledger-filter__body > .ledger-filter__field,
    .library-ap .ledger-filter__body > .ledger-filter__field,
    .office-ap .ledger-filter__body > .ledger-filter__field,
    .attendance-ap .ledger-filter__body > .ledger-filter__field,
    .academics-ap .ledger-filter__body > .ledger-filter__field,
    .exam-ap .ledger-filter__body > .ledger-filter__field,
    .webcms-ap .ledger-filter__body > .ledger-filter__field,
    .fees-ap .ledger-filter__body > .ledger-filter__field, .cms-ap .ledger-filter__body > .ledger-filter__field{
        max-width: none;
    }

    @media (max-width: 1199.98px) {.ledger-ap .ledger-filter__btn span,
    .postings-ap .ledger-filter__btn span,
    .alt-ap .ledger-filter__btn span,
    .als-ap .ledger-filter__btn span,
    .library-ap .ledger-filter__btn span,
    .office-ap .ledger-filter__btn span,
    .attendance-ap .ledger-filter__btn span,
    .academics-ap .ledger-filter__btn span,
    .exam-ap .ledger-filter__btn span,
    .webcms-ap .ledger-filter__btn span,
    .fees-ap .ledger-filter__btn span, .cms-ap .ledger-filter__btn span{
            /* keep text — still one line on tablet */
        }.ledger-ap .ledger-filter__field,
    .postings-ap .ledger-filter__field,
    .alt-ap .ledger-filter__field,
    .als-ap .ledger-filter__field,
    .library-ap .ledger-filter__field,
    .office-ap .ledger-filter__field,
    .attendance-ap .ledger-filter__field,
    .academics-ap .ledger-filter__field,
    .exam-ap .ledger-filter__field,
    .webcms-ap .ledger-filter__field,
    .fees-ap .ledger-filter__field, .cms-ap .ledger-filter__field{
            min-width: 4.5rem;
            max-width: 10rem;
        }
    }

    @media (max-width: 991.98px) {.ledger-ap .ledger-filter,
    .postings-ap .ledger-filter,
    .alt-ap .ledger-filter,
    .als-ap .ledger-filter,
    .library-ap .ledger-filter,
    .office-ap .ledger-filter,
    .attendance-ap .ledger-filter,
    .academics-ap .ledger-filter,
    .exam-ap .ledger-filter,
    .webcms-ap .ledger-filter,
    .fees-ap .ledger-filter, .cms-ap .ledger-filter{
            flex-wrap: wrap;
        }.ledger-ap .ledger-filter__body,
    .postings-ap .ledger-filter__body,
    .alt-ap .ledger-filter__body,
    .als-ap .ledger-filter__body,
    .library-ap .ledger-filter__body,
    .office-ap .ledger-filter__body,
    .attendance-ap .ledger-filter__body,
    .academics-ap .ledger-filter__body,
    .exam-ap .ledger-filter__body,
    .webcms-ap .ledger-filter__body,
    .fees-ap .ledger-filter__body, .cms-ap .ledger-filter__body{
            flex: 1 1 100%;
        }.ledger-ap .ledger-filter__grid,
    .postings-ap .ledger-filter__grid,
    .postings-ap .ledger-filter__grid--with-type,
    .postings-ap .ledger-filter__grid--single-date,
    .alt-ap .ledger-filter__grid,
    .als-ap .ledger-filter__grid,
    .library-ap .ledger-filter__grid,
    .office-ap .ledger-filter__grid,
    .library-ap .ledger-filter__grid--with-type,
    .office-ap .ledger-filter__grid--with-type,
    .library-ap .ledger-filter__grid--single-date,
    .office-ap .ledger-filter__grid--single-date,
    .attendance-ap .ledger-filter__grid,
    .attendance-ap .ledger-filter__grid--with-type,
    .attendance-ap .ledger-filter__grid--single-date,
    .academics-ap .ledger-filter__grid,
    .exam-ap .ledger-filter__grid,
    .webcms-ap .ledger-filter__grid,
    .fees-ap .ledger-filter__grid,
    .academics-ap .ledger-filter__grid--with-type,
    .exam-ap .ledger-filter__grid--with-type,
    .webcms-ap .ledger-filter__grid--with-type,
    .fees-ap .ledger-filter__grid--with-type,
    .academics-ap .ledger-filter__grid--single-date,
    .exam-ap .ledger-filter__grid--single-date,
    .webcms-ap .ledger-filter__grid--single-date,
    .fees-ap .ledger-filter__grid--single-date, .cms-ap .ledger-filter__grid--single-date{
            flex-wrap: wrap;
        }.ledger-ap .ledger-filter__actions,
    .postings-ap .ledger-filter__actions,
    .alt-ap .ledger-filter__actions,
    .als-ap .ledger-filter__actions,
    .library-ap .ledger-filter__actions,
    .office-ap .ledger-filter__actions,
    .attendance-ap .ledger-filter__actions,
    .academics-ap .ledger-filter__actions,
    .exam-ap .ledger-filter__actions,
    .webcms-ap .ledger-filter__actions,
    .fees-ap .ledger-filter__actions, .cms-ap .ledger-filter__actions{
            flex: 1 1 100%;
            justify-content: flex-start;
            padding-top: 0.15rem;
        }
    }

    @media (max-width: 575.98px) {.ledger-ap .ledger-filter__grid,
    .postings-ap .ledger-filter__grid,
    .postings-ap .ledger-filter__grid--with-type,
    .postings-ap .ledger-filter__grid--single-date,
    .alt-ap .ledger-filter__grid,
    .als-ap .ledger-filter__grid,
    .library-ap .ledger-filter__grid,
    .office-ap .ledger-filter__grid,
    .library-ap .ledger-filter__grid--with-type,
    .office-ap .ledger-filter__grid--with-type,
    .library-ap .ledger-filter__grid--single-date,
    .office-ap .ledger-filter__grid--single-date,
    .attendance-ap .ledger-filter__grid,
    .attendance-ap .ledger-filter__grid--with-type,
    .attendance-ap .ledger-filter__grid--single-date,
    .academics-ap .ledger-filter__grid,
    .exam-ap .ledger-filter__grid,
    .webcms-ap .ledger-filter__grid,
    .fees-ap .ledger-filter__grid,
    .academics-ap .ledger-filter__grid--with-type,
    .exam-ap .ledger-filter__grid--with-type,
    .webcms-ap .ledger-filter__grid--with-type,
    .fees-ap .ledger-filter__grid--with-type,
    .academics-ap .ledger-filter__grid--single-date,
    .exam-ap .ledger-filter__grid--single-date,
    .webcms-ap .ledger-filter__grid--single-date,
    .fees-ap .ledger-filter__grid--single-date, .cms-ap .ledger-filter__grid--single-date{
            flex-direction: column;
            align-items: stretch;
        }.ledger-ap .ledger-filter__field,
    .postings-ap .ledger-filter__field,
    .alt-ap .ledger-filter__field,
    .als-ap .ledger-filter__field,
    .ledger-ap .ledger-filter__field--kw,
    .postings-ap .ledger-filter__field--kw,
    .library-ap .ledger-filter__field,
    .office-ap .ledger-filter__field,
    .library-ap .ledger-filter__field--kw,
    .office-ap .ledger-filter__field--kw,
    .attendance-ap .ledger-filter__field,
    .attendance-ap .ledger-filter__field--kw,
    .academics-ap .ledger-filter__field,
    .exam-ap .ledger-filter__field,
    .webcms-ap .ledger-filter__field,
    .fees-ap .ledger-filter__field,
    .academics-ap .ledger-filter__field--kw,
    .exam-ap .ledger-filter__field--kw,
    .webcms-ap .ledger-filter__field--kw,
    .fees-ap .ledger-filter__field--kw, .cms-ap .ledger-filter__field--kw{
            max-width: none;
            width: 100%;
        }.ledger-ap .ledger-filter__field--sep,
    .postings-ap .ledger-filter__field--sep,
    .alt-ap .ledger-filter__field--sep,
    .library-ap .ledger-filter__field--sep,
    .office-ap .ledger-filter__field--sep,
    .attendance-ap .ledger-filter__field--sep,
    .academics-ap .ledger-filter__field--sep,
    .exam-ap .ledger-filter__field--sep,
    .webcms-ap .ledger-filter__field--sep,
    .fees-ap .ledger-filter__field--sep, .cms-ap .ledger-filter__field--sep{
            display: none !important;
        }.ledger-ap .ledger-filter__actions,
    .postings-ap .ledger-filter__actions,
    .alt-ap .ledger-filter__actions,
    .als-ap .ledger-filter__actions,
    .library-ap .ledger-filter__actions,
    .office-ap .ledger-filter__actions,
    .attendance-ap .ledger-filter__actions,
    .academics-ap .ledger-filter__actions,
    .exam-ap .ledger-filter__actions,
    .webcms-ap .ledger-filter__actions,
    .fees-ap .ledger-filter__actions, .cms-ap .ledger-filter__actions{
            flex-wrap: wrap;
        }
    }
</style>
