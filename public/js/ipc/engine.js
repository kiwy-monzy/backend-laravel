/**
 * Native JavaScript implementation of the IPC Engine.
 * This completely replaces the Rust WebAssembly module.
 */

function preset(agency) {
    // Generate a basic TANROADS preset contract
    return JSON.stringify({
        contract: {
            project_name: "New Project",
            contract_no: "CTR-000",
            agency: agency,
            employer: "TANROADS",
            contractor: "Contractor Ltd",
            engineer: "Engineer",
            signing_date: "",
            commencement_date: "",
            original_completion_date: "",
            total_bills: 1000000,
            contingency_provision: 0,
            vop_provision: 0,
            taxes_duties: 0,
            vat_rate: 0.18,
            advance_rate: 0.15,
            advance_recovery_rate: 0.20,
            advance_recovery_threshold: 0.20,
            payment_ceiling: 0.95,
            retention_rate: 0.10,
            retention_limit_rate: 0.05,
            bills: [
                { code: "1", name: "Preliminaries", boq_value: 100000, live_indices: [], non_adjustable: false },
                { code: "2", name: "Earthworks", boq_value: 900000, live_indices: [], non_adjustable: false }
            ]
        },
        indices: {
            portions: [
                {
                    label: "Local",
                    currency: "TZS",
                    proportion: 1.0,
                    fixed_coefficient: 0.15,
                    exchange_rate: 1.0,
                    elements: [
                        { code: "LL", description: "Labour", coefficient: 0.85, base_value: 100, letter: "bl" }
                    ]
                }
            ]
        },
        period: []
    });
}

function physical_progress(contract, period) {
    let total_bills = 0;
    contract.bills.forEach(b => total_bills += b.boq_value);
    if (total_bills <= 0) return 0;

    let progress = 0;
    period.entries.forEach(e => {
        const bill = contract.bills.find(b => b.code === e.code);
        if (bill) {
            const weight = bill.boq_value / total_bills;
            const p = Math.max(0, Math.min(1, e.progress || 0));
            progress += weight * p;
        }
    });
    return Math.max(0, Math.min(1, progress));
}

function compute(projectJson) {
    const p = JSON.parse(projectJson);
    const c = p.contract;
    
    let subtotal_before_vat = c.total_bills + c.contingency_provision + c.vop_provision + c.taxes_duties;
    let vat_amount = subtotal_before_vat * c.vat_rate;
    let contract_sum = subtotal_before_vat + vat_amount;
    let advance_amount = contract_sum * c.advance_rate;
    let retention_limit = contract_sum * c.retention_limit_rate;
    let payment_limit = contract_sum * c.payment_ceiling;
    let advance_recovery_start = contract_sum * c.advance_recovery_threshold;

    let derived = {
        subtotal_before_vat,
        vat_amount,
        specified_ps_total: 0,
        contract_sum,
        advance_amount,
        retention_limit,
        payment_limit,
        advance_recovery_start,
        total_bills: c.total_bills,
        bills_sum: c.bills.reduce((s, b) => s + b.boq_value, 0)
    };

    let cum_certified = 0;
    let cum_retention = 0;
    let cum_recovery = 0;

    const certs = (p.period || []).map(period => {
        const progress = physical_progress(c, period);
        let measured_works = 0;
        let net_adjustable = 0;
        
        period.entries.forEach(e => {
            measured_works += e.measured;
            const bill = c.bills.find(b => b.code === e.code);
            if (bill && !bill.non_adjustable) {
                net_adjustable += e.measured - (e.materials_on_site || 0) - (e.nominated_sub || 0) - (e.provisional_sum || 0);
            }
        });

        // Basic traditional factor implementation (simplified for now)
        let price_adjustment = 0; 
        let factor = net_adjustable > 0 ? price_adjustment / net_adjustable : 0;
        
        let subtotal_works = measured_works + price_adjustment + (period.contingency_released || 0) + (period.change_in_law || 0);

        let retention = 0;
        if (progress >= 1.0) {
            retention = -cum_retention * 0.5; // release half
        } else {
            retention = Math.min(subtotal_works * c.retention_rate, Math.max(0, retention_limit - cum_retention));
        }
        cum_retention += retention;

        let after_deductions = subtotal_works - retention - (period.employer_claims || 0) + (period.delay_interest || 0) + (period.contractor_claims || 0) + (period.other_taxes || 0);
        let vat = after_deductions * c.vat_rate;
        let gross = after_deductions + vat;

        let recovery = 0;
        if (cum_certified >= advance_recovery_start && cum_recovery < advance_amount) {
            recovery = Math.min(gross * c.advance_recovery_rate, advance_amount - cum_recovery);
        }
        cum_recovery += recovery;

        let net_payable = gross - recovery;
        cum_certified = Math.min(cum_certified + net_payable, payment_limit);

        return {
            number: period.number,
            month: period.month,
            measured_works,
            net_adjustable,
            adjustment_factor: factor,
            price_adjustment,
            subtotal_works,
            retention,
            cumulative_retention: cum_retention,
            subtotal_after_deductions: after_deductions,
            vat,
            gross,
            advance_recovery: recovery,
            cumulative_advance_recovery: cum_recovery,
            net_payable,
            certified_to_date: cum_certified,
            physical_progress: progress,
            planned_progress: period.planned_progress || null,
            spi: null,
            cpi: null
        };
    });

    let billsDto = c.bills.map(b => ({
        code: b.code,
        name: b.name,
        boq_value: b.boq_value,
        weight: c.total_bills > 0 ? b.boq_value / c.total_bills : 0,
        live_indices: b.live_indices || [],
        non_adjustable: !!b.non_adjustable
    }));

    return JSON.stringify({
        valid: true,
        errors: [],
        disagreements: [],
        period_count: p.period ? p.period.length : 0,
        derived,
        bills: billsDto,
        portions: [],
        variants: {
            single: certs,
            traditional: certs,
            multiple: certs
        }
    });
}

function formula(projectJson, periodIndex) {
    return JSON.stringify([]); // Placeholder for formula terms
}

export default {
    preset,
    compute,
    formula
};
