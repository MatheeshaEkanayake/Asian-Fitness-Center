// Weight/height are stored canonically as kg/cm (see MemberFormPage.jsx);
// these convert between that canonical value and whichever unit staff
// currently have selected in the form's unit toggle.

const KG_PER_LB = 0.45359237
const CM_PER_IN = 2.54

export function kgToLb(kg) {
  return kg / KG_PER_LB
}

export function lbToKg(lb) {
  return lb * KG_PER_LB
}

export function cmToFtIn(cm) {
  const totalInches = cm / CM_PER_IN
  const ft = Math.floor(totalInches / 12)
  const inch = totalInches - ft * 12
  return { ft, inch }
}

export function ftInToCm(ft, inch) {
  return (ft * 12 + inch) * CM_PER_IN
}

export function round(value, decimals = 1) {
  const factor = 10 ** decimals
  return Math.round(value * factor) / factor
}
