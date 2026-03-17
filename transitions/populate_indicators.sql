-- transitions/populate_indicators.sql

-- First, get section_ids (assuming they were inserted in order)
-- T1 section_id = 1, T2 = 2, T3 = 3, etc.

-- T1 Indicators (section_id = 1)
INSERT INTO transition_indicators (section_id, indicator_code, indicator_text, display_order) VALUES
(1, 'T1.1', 'Does the county have a legally constituted mechanism that oversees the health department? (e.g. County assembly health committee)', 1),
(1, 'T1.2', 'Does the county have an overall vision for the County Department of Health (CDOH) that is overseen by the County assembly health committee?', 2),
(1, 'T1.3', 'Are the roles of the County assembly health committee well-defined in the county health system?', 3),
(1, 'T1.4', 'Are County assembly health committee meetings held regularly as stipulated; decisions documented; and reflect accountability and resource stewardship?', 4),
(1, 'T1.5', 'Does the County assembly health committee composition include members who are recognized for leadership and/or area of expertise and are representative of stakeholders including PLHIV/TB patients?', 5),
(1, 'T1.6', 'Does the County assembly health committee ensure that public interest is considered in decision making?', 6),
(1, 'T1.7', 'How committed and accountable is the County assembly health committee in following up on agreed action items?', 7),
(1, 'T1.8', 'Does the County assembly health committee have a risk management policy/framework?', 8),
(1, 'T1.9', 'How much oversight is given to HIV/TB activities in the county by the health committee of the county assembly?', 9),
(1, 'T1.10', 'Is the leadership arrangement/structure for the HIV/TB program adequate to increase coverage and quality of HIV/TB services?', 10),
(1, 'T1.11', 'Does the HIV/TB program planning and funding allow for sustainability?', 11);

-- T2 Indicators (section_id = 2)
INSERT INTO transition_indicators (section_id, indicator_code, indicator_text, display_order) VALUES
(2, 'T2.1', 'Is the CHMT responsive to the requirements of the County\'s Oversight structures, i.e. County assembly health committee?', 1),
(2, 'T2.2', 'Is the CHMT accountable to clients/patients seeking services within the county?', 2),
(2, 'T2.3', 'Is the CHMT involving the private sector and community based organizations in the planning of health services including HIV/TB services?', 3),
(2, 'T2.4', 'Are CHMT meetings held regularly as stipulated; decisions documented including for the HIV/TB program; and reflect accountability and resource stewardship?', 4),
(2, 'T2.5', 'Is the CHMT implementing policies and regulations set by national level?', 5),
(2, 'T2.6', 'Does the CHMT hold joint monitoring teams and joint high-level meetings with development partners supporting the county?', 6),
(2, 'T2.7', 'Does the CHMT plan and manage health services to meet local needs?', 7),
(2, 'T2.8', 'Does the CHMT mobilize local resources for the HIV/TB program?', 8),
(2, 'T2.9', 'Is the CHMT involved in the supervision of HIV/TB services in the county?', 9),
(2, 'T2.10', 'Has the CHMT ensured that the leadership arrangement/structure for the HIV/TB program is adequate to increase coverage and quality of HIV/TB services?', 10),
(2, 'T2.11', 'Has the CHMT ensured that the HIV/TB program planning and funding allow for sustainability?', 11);

-- T3 Indicators (section_id = 3)
INSERT INTO transition_indicators (section_id, indicator_code, indicator_text, display_order) VALUES
(3, 'T3.1', 'Creating a costed county annual work plan for HIV/TB services', 1),
(3, 'T3.2', 'Identifying key HIV program priorities that sustains good coverage and high HIV service quality', 2),
(3, 'T3.3', 'Track implementation of the costed county annual work plan for HIV/TB services', 3),
(3, 'T3.4', 'Identifying HRH needs for HIV/TB that will support the delivery of the agreed package of activities', 4),
(3, 'T3.5', 'Having in place a system for forecasting, including HRH needs for HIV/TB', 5),
(3, 'T3.6', 'Coordinating the scope of activities and resource contributions of all partners for HIV/TB in county', 6),
(3, 'T3.7', 'Convening meetings with key county HIV/TB services program staff and implementing partners to review performance', 7),
(3, 'T3.8', 'Convening meetings with community HIV/TB stakeholders to review community needs', 8),
(3, 'T3.9', 'Convening to review program performance for HIV/TB', 9),
(3, 'T3.10', 'Providing technical guidance for county AIDS/TB coordination', 10),
(3, 'T3.11', 'Providing support to the County AIDS Committee', 11);

-- T4A Indicators (section_id = 4)
INSERT INTO transition_indicators (section_id, indicator_code, indicator_text, display_order) VALUES
(4, 'T4A.1', 'Developing the county HIV/TB programme routine supervision plan', 1),
(4, 'T4A.2', 'Arranging logistics, including vehicle and/or fuel', 2),
(4, 'T4A.3', 'Conducting routine supervision visits to county (public)/private/faith-based facilities', 3),
(4, 'T4A.4', 'Completing supervision checklist', 4),
(4, 'T4A.5', 'Mobilizing support to address issues identified during supervision', 5),
(4, 'T4A.6', 'Financial facilitation for county supervision (paying allowances to supervisors)', 6),
(4, 'T4A.7', 'Developing the action plan and following up on issues identified during the supervision', 7),
(4, 'T4A.8', 'Planning for staff mentorship including cross learning visits', 8),
(4, 'T4A.9', 'Spending time with staff to identify individual\'s strengths', 9),
(4, 'T4A.10', 'Identifying and working with facility staff to pursue mentorship goals', 10),
(4, 'T4A.11', 'Paying for mentorship activities', 11),
(4, 'T4A.12', 'Documenting outcomes of the mentorship', 12);

-- T4B Indicators (section_id = 5)
INSERT INTO transition_indicators (section_id, indicator_code, indicator_text, display_order) VALUES
(5, 'T4B.1', 'Developing the county HIV/TB supervision plan', 1),
(5, 'T4B.2', 'Arranging logistics, including vehicle and/or fuel', 2),
(5, 'T4B.3', 'Conducting supervision visits to county (public)/private/faith-based facilities', 3),
(5, 'T4B.4', 'Completing supervision forms', 4),
(5, 'T4B.5', 'Mobilizing support to address issues identified during supervision', 5),
(5, 'T4B.6', 'Financial facilitation for county supervision (paying allowances to supervisors)', 6),
(5, 'T4B.7', 'Developing the action plan and following up on issues identified during the supervision', 7),
(5, 'T4B.8', 'Planning for staff mentorship including cross learning visits', 8),
(5, 'T4B.9', 'Spending time with staff to identify individual\'s strengths', 9),
(5, 'T4B.10', 'Identifying and working with facility staff to pursue mentorship goals', 10),
(5, 'T4B.11', 'Paying for mentorship activities', 11),
(5, 'T4B.12', 'Documenting outcomes of the mentorship', 12);

-- Continue for all other sections...
-- For brevity, I'll show the pattern for T5A/T5B through T19A/T19B

-- T5A (section_id = 6)
INSERT INTO transition_indicators (section_id, indicator_code, indicator_text, display_order) VALUES
(6, 'T5A.1', 'Developing the county RRI, LEAP, Surge or SIMS plan or any other initiative', 1),
(6, 'T5A.2', 'Arranging logistics, including vehicle and/or fuel', 2),
(6, 'T5A.3', 'Conducting LEAP, SURGE, SIMS or RRI visits to public/private/faith based facilities', 3),
(6, 'T5A.4', 'Completing relevant initiative tools / reporting templates', 4),
(6, 'T5A.5', 'Mobilizing support to address issues identified during site visits', 5),
(6, 'T5A.6', 'Financial facilitation for site visits (paying allowances to the team)', 6),
(6, 'T5A.7', 'Developing the action plan and following up on issues identified during site visits', 7),
(6, 'T5A.8', 'Reporting special initiative implementation progress to higher levels', 8);

-- T5B (section_id = 7)
INSERT INTO transition_indicators (section_id, indicator_code, indicator_text, display_order) VALUES
(7, 'T5B.1', 'Developing the county RRI, LEAP, Surge or SIMS plan or any other initiative', 1),
(7, 'T5B.2', 'Arranging logistics, including vehicle and/or fuel', 2),
(7, 'T5B.3', 'Conducting LEAP, SURGE, SIMS or RRI visits to public/private/faith based facilities', 3),
(7, 'T5B.4', 'Completing relevant initiative tools/ reporting templates', 4),
(7, 'T5B.5', 'Mobilizing support to address issues identified during site visits', 5),
(7, 'T5B.6', 'Financial facilitation for site visits (paying allowances to the team)', 6),
(7, 'T5B.7', 'Developing the action plan and following up on issues identified during site visits', 7),
(7, 'T5B.8', 'Reporting special initiative implementation progress to higher levels', 8);

-- T6A (section_id = 8)
INSERT INTO transition_indicators (section_id, indicator_code, indicator_text, display_order) VALUES
(8, 'T6A.1', 'Selecting priorities and developing / adapting QI plan', 1),
(8, 'T6A.2', 'Training facility staff', 2),
(8, 'T6A.3', 'Providing technical support to QI teams', 3),
(8, 'T6A.4', 'Reviewing/tracking facility QI reports', 4),
(8, 'T6A.5', 'Funding QI Initiatives', 5),
(8, 'T6A.6', 'Other support QI activities', 6),
(8, 'T6A.7', 'Convening/managing county-wide QI forum', 7);

-- T6B (section_id = 9)
INSERT INTO transition_indicators (section_id, indicator_code, indicator_text, display_order) VALUES
(9, 'T6B.1', 'Selecting priorities and developing/adapting QI plan', 1),
(9, 'T6B.2', 'Training facility staff', 2),
(9, 'T6B.3', 'Providing technical support to QI teams', 3),
(9, 'T6B.4', 'Reviewing/tracking facility QI reports', 4),
(9, 'T6B.5', 'Funding QI Initiatives', 5),
(9, 'T6B.6', 'Other support QI activities', 6),
(9, 'T6B.7', 'Convening/managing county-wide QI forum', 7);

-- Continue this pattern for all sections up to T19B...
-- For IO sections (section_ids 36-38)

-- IO1 (section_id = 36)
INSERT INTO transition_indicators (section_id, indicator_code, indicator_text, display_order) VALUES
(36, 'IO1.1', 'Does the county routinely develop HIV/TB AWPs that are based on the CIDP?', 1),
(36, 'IO1.2', 'Has the county costed its HIV/TB AWP and integrated it with the last national budget request?', 2),
(36, 'IO1.3', 'Are different levels of HIV/TB treatment staff involved in the development of the HIV/TB AWP?', 3),
(36, 'IO1.4', 'Are stakeholders from HIV/TB programs and PLHIV/TB involved in the development of HIV/TB AWPs?', 4),
(36, 'IO1.5', 'Is the implementation of the county HIV/TB work plan monitored and tracked by the County health team?', 5);

-- IO2 (section_id = 37)
INSERT INTO transition_indicators (section_id, indicator_code, indicator_text, display_order) VALUES
(37, 'IO2.1', 'Does the CDOH have a list of all active HIV/TB services CSOs and implementing partners in the county with contact information?', 1),
(37, 'IO2.2', 'Does the county provide a functional forum for experience exchange on at least a quarterly basis?', 2),
(37, 'IO2.3', 'Does the county disseminate information, standards and best practices to implementers and stakeholders in a timely manner?', 3),
(37, 'IO2.4', 'Does the county work to ensure a rational geographic distribution, program coverage and scale-up of HIV/TB services?', 4);

-- IO3 (section_id = 38)
INSERT INTO transition_indicators (section_id, indicator_code, indicator_text, display_order) VALUES
(38, 'IO3.1', 'Is the county strategic plan aligned to the National HIV/TB framework developed by NACC?', 1),
(38, 'IO3.2', 'Does the county team perceive the national framework for HIV/TB care and treatment programs is relevant to their county needs?', 2),
(38, 'IO3.3', 'Is the policy formulation and capacity building functions of NACC/NASCOP to the county helpful in resolving implementation challenges?', 3),
(38, 'IO3.4', 'Is the county team aware of its HIV/TB program service targets? If yes, are they using this data to inform annual HIV/TB plans?', 4),
(38, 'IO3.5', 'Does the county team perceive that the HIV service targets/objectives expected of their county are realistic?', 5),
(38, 'IO3.6', 'Is the financial grant from the national level adequate to meet the HIV/TB service targets expected of the county team?', 6);