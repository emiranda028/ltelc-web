#!/usr/bin/env python3
"""Genera en/index.html y pt/index.html a partir de index.html (español).

Uso:  python3 i18n/build.py
Cada fila de T es (español, inglés, portugués). El texto en español tiene que
existir tal cual en index.html; si no aparece, el script avisa y se detiene.
"""
import os, re, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, 'index.html')

T = [
# --- head ---
('<title>LTELC Consultora</title>', '<title>LTELC Consulting</title>', '<title>LTELC Consultoria</title>'),
('content="LTELC (Lo Tengo en la Cabeza) — Consultora argentina que integra la operación con la administración: sistemas de administración, balances contables, tableros de indicadores, software a medida, automatización e integración."',
 'content="LTELC (Lo Tengo en la Cabeza, “I have it in my head”) — Argentine consultancy that brings operations and administration together: management systems, financial statements, KPI dashboards, custom software, automation and integration."',
 'content="LTELC (Lo Tengo en la Cabeza, “está na minha cabeça”) — Consultoria argentina que integra a operação com a administração: sistemas de gestão, balanços contábeis, painéis de indicadores, software sob medida, automação e integração."'),
('content="Todo lo que tu organización tiene en la cabeza, lo convertimos en sistema."', 'content="Everything your organization keeps in its head, we turn into a system."', 'content="Tudo o que sua organização tem na cabeça, nós transformamos em sistema."'),
# --- navegación ---
('aria-label="LTELC, inicio"', 'aria-label="LTELC, home"', 'aria-label="LTELC, início"'),
('<nav aria-label="Secciones">', '<nav aria-label="Sections">', '<nav aria-label="Seções">'),
('<li><a href="#soluciones">Soluciones</a></li>', '<li><a href="#soluciones">Solutions</a></li>', '<li><a href="#soluciones">Soluções</a></li>'),
('<li><a href="#webapp">Web y app</a></li>', '<li><a href="#webapp">Web &amp; app</a></li>', '<li><a href="#webapp">Web e app</a></li>'),
('<li><a href="#proyectos">Proyectos</a></li>', '<li><a href="#proyectos">Projects</a></li>', '<li><a href="#proyectos">Projetos</a></li>'),
('<li><a href="#nosotros">Nosotros</a></li>', '<li><a href="#nosotros">About</a></li>', '<li><a href="#nosotros">Sobre nós</a></li>'),
('<li><a href="#equipo">Equipo</a></li>', '<li><a href="#equipo">Team</a></li>', '<li><a href="#equipo">Equipe</a></li>'),
('<div class="lang" aria-label="Idioma">', '<div class="lang" aria-label="Language">', '<div class="lang" aria-label="Idioma">'),
('<a class="pill pill-blue" href="#contacto">Contacto</a>', '<a class="pill pill-blue" href="#contacto">Contact</a>', '<a class="pill pill-blue" href="#contacto">Contato</a>'),
# --- héroe ---
('aria-label="Presentación"', 'aria-label="Introduction"', 'aria-label="Apresentação"'),
('Consultora argentina · Sistemas, datos y software', 'Argentine consultancy · Systems, data &amp; software', 'Consultoria argentina · Sistemas, dados e software'),
('<span class="l l1">Todo lo que tu organización <b>tiene en la cabeza</b>…</span><span class="l l2">…lo convertimos en <b>sistema.</b></span>',
 '<span class="l l1">Everything your organization <b>keeps in its head</b>…</span><span class="l l2">…we turn into a <b>system.</b></span>',
 '<span class="l l1">Tudo o que sua organização <b>tem na cabeça</b>…</span><span class="l l2">…nós transformamos em <b>sistema.</b></span>'),
('<p>Integramos la operación con la administración: entendemos tu negocio de punta a punta y lo convertimos en sistemas, balances e indicadores para organizaciones que no pueden detenerse.</p>',
 '<p>We bring operations and administration together: we understand your business end to end and turn it into systems, financial statements and KPIs for organizations that cannot stop.</p>',
 '<p>Integramos a operação com a administração: entendemos seu negócio de ponta a ponta e o transformamos em sistemas, balanços e indicadores para organizações que não podem parar.</p>'),
('Agendá un diagnóstico sin cargo', 'Book a free assessment', 'Agende um diagnóstico gratuito'),
('>Ver proyectos<', '>See projects<', '>Ver projetos<'),
('>Deslizá<', '>Scroll<', '>Role<'),
# --- manifiesto ---
('Somos <b>LTELC</b>, la sigla de <b>Lo Tengo en la Cabeza</b>. Nuestra especialidad es <b>integrar la operación con la administración</b>: entendemos el negocio de punta a punta y convertimos lo que hoy vive en planillas sueltas y en la memoria de tu equipo en <b>sistemas, balances e indicadores</b> que funcionan todos los días.',
 'We are <b>LTELC</b>, short for <b>Lo Tengo en la Cabeza</b>, Spanish for “I have it in my head”. Our specialty is <b>bringing operations and administration together</b>: we understand the business end to end and turn what lives in loose spreadsheets and in your team’s memory into <b>systems, financial statements and KPIs</b> that work every day.',
 'Somos a <b>LTELC</b>, sigla de <b>Lo Tengo en la Cabeza</b>, “está na minha cabeça” em espanhol. Nossa especialidade é <b>integrar a operação com a administração</b>: entendemos o negócio de ponta a ponta e transformamos o que hoje vive em planilhas soltas e na memória da sua equipe em <b>sistemas, balanços e indicadores</b> que funcionam todos os dias.'),
('>Nuestras soluciones<', '>Our solutions<', '>Nossas soluções<'),
('>Por qué LTELC<', '>Why LTELC<', '>Por que LTELC<'),
('Organizaciones que confiaron en nosotros', 'Organizations that trust us', 'Organizações que confiaram em nós'),
('alt="Sheraton Bariloche y Sheraton Mar del Plata"', 'alt="Sheraton Bariloche and Sheraton Mar del Plata"', 'alt="Sheraton Bariloche e Sheraton Mar del Plata"'),
('<p class="kicker">Nuestro principio</p>', '<p class="kicker">Our principle</p>', '<p class="kicker">Nosso princípio</p>'),
('No cambiamos tu forma de trabajar. <b>La tecnología se adapta a tu negocio.</b>', 'We don’t change the way you work. <b>Technology adapts to your business.</b>', 'Não mudamos a sua forma de trabalhar. <b>A tecnologia se adapta ao seu negócio.</b>'),
('Partimos de cómo funciona hoy tu organización, con su gente, sus tiempos y sus herramientas, e integramos la tecnología a ese negocio que ya conocen y llevan adelante.', 'We start from how your organization works today, with its people, its pace and its tools, and integrate technology into the business you already know and run.', 'Partimos de como sua organização funciona hoje, com suas pessoas, seus tempos e suas ferramentas, e integramos a tecnologia ao negócio que vocês já conhecem e conduzem.'),
('<b>Nos adaptamos a lo que hay</b><span>Respetamos los circuitos, las planillas y los sistemas que ya usan, y construimos sobre eso.</span>', '<b>We adapt to what is already there</b><span>We respect the workflows, spreadsheets and systems you already use, and build on top of them.</span>', '<b>Nos adaptamos ao que já existe</b><span>Respeitamos os fluxos, as planilhas e os sistemas que vocês já usam, e construímos sobre isso.</span>'),
('<b>Integramos, no reemplazamos</b><span>La tecnología se suma al negocio tal como lo llevan, sin imponer procesos nuevos.</span>', '<b>We integrate, we don’t replace</b><span>Technology joins the business the way you run it, without imposing new processes.</span>', '<b>Integramos, não substituímos</b><span>A tecnologia se soma ao negócio do jeito que vocês o conduzem, sem impor processos novos.</span>'),
('<b>Para todas las personas</b><span>No importa la edad ni la experiencia con la tecnología: acompañamos a cada uno hasta que lo use con confianza.</span>', '<b>For everyone</b><span>Age and tech experience don’t matter: we support each person until they use it with confidence.</span>', '<b>Para todas as pessoas</b><span>Não importa a idade nem a experiência com tecnologia: acompanhamos cada um até que use com confiança.</span>'),
# --- soluciones ---
('<p class="kicker">Soluciones</p><h2>Cinco ejes para <b>ordenar, medir y automatizar</b> tu organización</h2>',
 '<p class="kicker">Solutions</p><h2>Five pillars to <b>organize, measure and automate</b> your organization</h2>',
 '<p class="kicker">Soluções</p><h2>Cinco eixos para <b>organizar, medir e automatizar</b> sua organização</h2>'),
('Desde la carga del primer comprobante hasta el indicador que mira la dirección. Construimos sobre lo que ya tenés, por etapas.',
 'From the first invoice entered to the KPI the board looks at. We build on what you already have, step by step.',
 'Do lançamento do primeiro comprovante ao indicador que a diretoria acompanha. Construímos sobre o que você já tem, por etapas.'),
('<h3>Sistemas de administración completos</h3>', '<h3>End-to-end management systems</h3>', '<h3>Sistemas de gestão completos</h3>'),
('Compras, ventas, stock, cuentas corrientes, caja y bancos y facturación electrónica en un solo sistema.',
 'Purchasing, sales, inventory, accounts, cash and banks, and electronic invoicing in a single system.',
 'Compras, vendas, estoque, contas correntes, caixa e bancos e faturamento eletrônico em um só sistema.'),
('<span>Compras</span><span>Ventas</span><span>Stock</span><span>Tesorería</span><span>ARCA</span>',
 '<span>Purchasing</span><span>Sales</span><span>Inventory</span><span>Treasury</span><span>E-invoicing</span>',
 '<span>Compras</span><span>Vendas</span><span>Estoque</span><span>Tesouraria</span><span>Nota fiscal</span>'),
('<h3>Balances y contabilidad</h3>', '<h3>Accounting &amp; financial statements</h3>', '<h3>Contabilidade e balanços</h3>'),
('Estados contables, conciliaciones y cierres desde los datos reales.', 'Financial statements, reconciliations and month-end closing from real data.', 'Demonstrações contábeis, conciliações e fechamentos a partir dos dados reais.'),
('<h3>Indicadores e impacto de datos</h3>', '<h3>KPIs &amp; data impact</h3>', '<h3>Indicadores e impacto dos dados</h3>'),
('Tableros en Power BI o a medida que muestran qué pasó, por qué y cuánto impacta.', 'Power BI or custom dashboards that show what happened, why and how much it matters.', 'Painéis em Power BI ou sob medida que mostram o que aconteceu, por quê e quanto impacta.'),
('<h3>Apps web a medida</h3>', '<h3>Custom web apps</h3>', '<h3>Apps web sob medida</h3>'),
('Funcionan en la compu y en el celular: turnos, reservas, legajos, portales.', 'They run on desktop and mobile: scheduling, bookings, HR records, portals.', 'Funcionam no computador e no celular: agendas, reservas, cadastros, portais.'),
('<h3>Automatización, integración e IA</h3>', '<h3>Automation, integration &amp; AI</h3>', '<h3>Automação, integração e IA</h3>'),
('Sistemas conectados, reportes que se arman solos y lectura de comprobantes con IA.', 'Connected systems, self-building reports and AI document reading.', 'Sistemas conectados, relatórios que se montam sozinhos e leitura de comprovantes com IA.'),
('<b>También hacemos</b><span>Tableros en Power BI</span><span>Conciliaciones bancarias y contables</span><span>Proyectos para organizaciones de la sociedad civil</span><span>Campañas electorales</span><span>Encuestas y opinión pública</span>',
 '<b>We also do</b><span>Power BI dashboards</span><span>Bank &amp; account reconciliations</span><span>Projects for civil society organizations</span><span>Electoral campaigns</span><span>Polling &amp; public opinion</span>',
 '<b>Também fazemos</b><span>Painéis em Power BI</span><span>Conciliações bancárias e contábeis</span><span>Projetos para organizações da sociedade civil</span><span>Campanhas eleitorais</span><span>Pesquisas e opinião pública</span>'),
# --- cifras ---
('LTELC en números', 'LTELC in numbers', 'LTELC em números'),
('Sistemas que sostienen <b>operaciones reales</b>, todos los días', 'Systems that run <b>real operations</b>, every day', 'Sistemas que sustentam <b>operações reais</b>, todos os dias'),
('<span>sistemas y plataformas en producción</span>', '<span>systems and platforms in production</span>', '<span>sistemas e plataformas em produção</span>'),
('<span>sectores: transporte, puertos, salud, hotelería y agua</span>', '<span>sectors: transport, ports, healthcare, hospitality and water</span>', '<span>setores: transporte, portos, saúde, hotelaria e água</span>'),
('<span>países analizados en el Atlas Político Mundial</span>', '<span>countries analyzed in the World Political Atlas</span>', '<span>países analisados no Atlas Político Mundial</span>'),
('<span>hoteles de Marriott y Sheraton en Buenos Aires, Bariloche y Mar del Plata</span>', '<span>Marriott and Sheraton hotels in Buenos Aires, Bariloche and Mar del Plata</span>', '<span>hotéis Marriott e Sheraton em Buenos Aires, Bariloche e Mar del Plata</span>'),
# --- sectores ---
('<p class="kicker">Sectores</p>', '<p class="kicker">Industries</p>', '<p class="kicker">Setores</p>'),
('<h2>Donde la información <b>no puede fallar</b></h2>', '<h2>Where information <b>cannot fail</b></h2>', '<h2>Onde a informação <b>não pode falhar</b></h2>'),
('En la vía, en el puerto, en el hospital, en la recepción del hotel y en el servicio de agua. Conocemos las operaciones que no se detienen.',
 'On the railway, at the port, in the hospital, at the hotel front desk and in the water utility. We know operations that never stop.',
 'Na ferrovia, no porto, no hospital, na recepção do hotel e no serviço de água. Conhecemos as operações que não param.'),
('alt="Formación ferroviaria"', 'alt="Train"', 'alt="Trem"'),
('<h3>Transporte ferroviario</h3><p>Presupuesto, ejecución y planificación plurianual.</p>', '<h3>Rail transport</h3><p>Budgeting, execution and multi-year planning.</p>', '<h3>Transporte ferroviário</h3><p>Orçamento, execução e planejamento plurianual.</p>'),
('alt="Puerto visto desde el aire"', 'alt="Aerial view of a port"', 'alt="Porto visto do alto"'),
('<h3>Puertos y practicaje</h3><p>Bitácora: guardia, operación y facturación sin papel.</p>', '<h3>Ports &amp; marine pilotage</h3><p>Bitácora: dispatch desk, operations and billing, paperless.</p>', '<h3>Portos e praticagem</h3><p>Bitácora: plantão, operação e faturamento sem papel.</p>'),
('alt="Fachada del Hospital El Cruce"', 'alt="Hospital El Cruce building"', 'alt="Fachada do Hospital El Cruce"'),
('<h3>Salud</h3><p>Administración integral del Hospital El Cruce.</p>', '<h3>Healthcare</h3><p>Integrated administration for Hospital El Cruce.</p>', '<h3>Saúde</h3><p>Gestão administrativa integrada do Hospital El Cruce.</p>'),
('alt="Fachada del City Express Plus by Marriott Buenos Aires"', 'alt="City Express Plus by Marriott Buenos Aires building"', 'alt="Fachada do City Express Plus by Marriott Buenos Aires"'),
('<h3>Hotelería</h3><p>Marriott Buenos Aires, Sheraton Bariloche, Sheraton Mar del Plata y City Express Plus by Marriott.</p>', '<h3>Hospitality</h3><p>Marriott Buenos Aires, Sheraton Bariloche, Sheraton Mar del Plata and City Express Plus by Marriott.</p>', '<h3>Hotelaria</h3><p>Marriott Buenos Aires, Sheraton Bariloche, Sheraton Mar del Plata e City Express Plus by Marriott.</p>'),
# --- casos ---
('<p class="kicker">Todo en 1</p><h2 id="casesT">Un solo sistema, <b>de punta a punta</b></h2>', '<p class="kicker">All in one</p><h2 id="casesT">One system, <b>end to end</b></h2>', '<p class="kicker">Tudo em 1</p><h2 id="casesT">Um só sistema, <b>de ponta a ponta</b></h2>'),
('Donde había papeles, planillas y sistemas que no se hablaban, hoy hay una sola plataforma que sincroniza todo y ayuda a decidir.', 'Where there used to be paper, spreadsheets and systems that did not talk to each other, there is now one platform that syncs everything and supports decisions.', 'Onde havia papéis, planilhas e sistemas que não se falavam, hoje há uma só plataforma que sincroniza tudo e ajuda a decidir.'),
('<span class="tag">Puerto · Practicaje y Pilotaje Buenos Aires</span>', '<span class="tag">Port · Practicaje y Pilotaje Buenos Aires</span>', '<span class="tag">Porto · Practicaje y Pilotaje Buenos Aires</span>'),
('<h3><b>Bitácora</b>, la operación del puerto sin papel</h3>', '<h3><b>Bitácora</b>, paperless port operations</h3>', '<h3><b>Bitácora</b>, a operação do porto sem papel</h3>'),
('Resuelve todo de punta a punta: desde la guardia y la operación diaria hasta la facturación. Se dejó el papel y cada registro se sincroniza solo con el sistema de facturación.', 'It covers everything end to end: from the dispatch desk and daily operations to billing. Paper is gone and every record syncs automatically with the billing system.', 'Resolve tudo de ponta a ponta: do plantão e da operação diária ao faturamento. O papel ficou para trás e cada registro se sincroniza sozinho com o sistema de faturamento.'),
('<span>Sin papel</span><span>Sincronización automática</span><span>Facturación integrada</span><span>Todo en 1</span>', '<span>Paperless</span><span>Automatic sync</span><span>Integrated billing</span><span>All in one</span>', '<span>Sem papel</span><span>Sincronização automática</span><span>Faturamento integrado</span><span>Tudo em 1</span>'),
('<b>Guardia</b><small>Turnos, novedades y asignaciones</small>', '<b>Dispatch desk</b><small>Shifts, updates and assignments</small>', '<b>Plantão</b><small>Turnos, novidades e designações</small>'),
('<b>Operación</b><small>Maniobras y servicios registrados en el momento</small>', '<b>Operations</b><small>Maneuvers and services logged in real time</small>', '<b>Operação</b><small>Manobras e serviços registrados na hora</small>'),
('<b>Sincronización</b><small>Cada dato se carga una sola vez</small>', '<b>Sync</b><small>Every record is entered only once</small>', '<b>Sincronização</b><small>Cada dado é lançado uma única vez</small>'),
('<b>Facturación</b><small>Comprobantes generados desde la operación</small>', '<b>Billing</b><small>Invoices generated from operations</small>', '<b>Faturamento</b><small>Notas geradas a partir da operação</small>'),
('<span><i></i>Contabilidad</span><span><i></i>Finanzas</span><span><i></i>Patrimonio</span>', '<span><i></i>Accounting</span><span><i></i>Finance</span><span><i></i>Fixed assets</span>', '<span><i></i>Contabilidade</span><span><i></i>Finanças</span><span><i></i>Patrimônio</span>'),
('<span><i></i>Cuentas a pagar</span><span><i></i>Impuestos</span><span><i></i>RRHH</span>', '<span><i></i>Accounts payable</span><span><i></i>Taxes</span><span><i></i>HR</span>', '<span><i></i>Contas a pagar</span><span><i></i>Impostos</span><span><i></i>RH</span>'),
('<b>Tablero para decidir</b><small>Toda la administración en una sola vista</small>', '<b>Decision dashboard</b><small>All of administration in a single view</small>', '<b>Painel para decidir</b><small>Toda a administração em uma só visão</small>'),
('<span class="tag">Salud pública · Hospital El Cruce</span>', '<span class="tag">Public healthcare · Hospital El Cruce</span>', '<span class="tag">Saúde pública · Hospital El Cruce</span>'),
('<h3>Toda la administración del hospital, <b>en un solo sistema</b></h3>', '<h3>The hospital’s entire administration, <b>in one system</b></h3>', '<h3>Toda a administração do hospital, <b>em um só sistema</b></h3>'),
('Contabilidad, finanzas, patrimonio, cuentas a pagar, impuestos y recursos humanos dejaron de ser sistemas separados. Hoy funcionan integrados, con información consolidada para tomar decisiones con facilidad.', 'Accounting, finance, fixed assets, accounts payable, taxes and human resources are no longer separate systems. They now work together, with consolidated information that makes decisions easy.', 'Contabilidade, finanças, patrimônio, contas a pagar, impostos e recursos humanos deixaram de ser sistemas separados. Hoje funcionam integrados, com informação consolidada para decidir com facilidade.'),
('<span>6 áreas integradas</span><span>Información consolidada</span><span>Decisiones con datos</span>', '<span>6 integrated areas</span><span>Consolidated information</span><span>Data-driven decisions</span>', '<span>6 áreas integradas</span><span>Informação consolidada</span><span>Decisões com dados</span>'),
# --- web y app ---
('<p class="kicker">Web y app</p>', '<p class="kicker">Web &amp; app</p>', '<p class="kicker">Web e app</p>'),
('Una sola aplicación, <b>en la compu y en el celular</b>', 'One application, <b>on desktop and on mobile</b>', 'Um só aplicativo, <b>no computador e no celular</b>'),
('Desarrollamos aplicaciones web: se usan desde cualquier navegador, se adaptan a cada pantalla y mantienen los datos sincronizados entre la oficina y el campo.', 'We build web applications: they run in any browser, adapt to every screen and keep data in sync between the office and the field.', 'Desenvolvemos aplicativos web: funcionam em qualquer navegador, se adaptam a cada tela e mantêm os dados sincronizados entre o escritório e o campo.'),
('<b>Se adapta a cualquier pantalla</b><span>Computadora, tablet o celular, con la misma información.</span>', '<b>Fits any screen</b><span>Desktop, tablet or phone, with the same information.</span>', '<b>Se adapta a qualquer tela</b><span>Computador, tablet ou celular, com a mesma informação.</span>'),
('<b>Sin instalar nada</b><span>Se entra desde el navegador y se puede agregar al inicio del celular como una app.</span>', '<b>Nothing to install</b><span>Open it in the browser and add it to your phone’s home screen like an app.</span>', '<b>Sem instalar nada</b><span>Acesse pelo navegador e adicione à tela inicial do celular como um app.</span>'),
('<b>Acceso seguro por usuario</b><span>Cada persona ve lo que le corresponde según su perfil.</span>', '<b>Secure user access</b><span>Each person sees what their role allows.</span>', '<b>Acesso seguro por usuário</b><span>Cada pessoa vê o que corresponde ao seu perfil.</span>'),
('<b>Datos en tiempo real</b><span>Lo que se carga en la guardia o el mostrador lo ve la dirección al instante.</span>', '<b>Real-time data</b><span>What is entered at the front desk is seen by management instantly.</span>', '<b>Dados em tempo real</b><span>O que é lançado no plantão ou no balcão a diretoria vê na hora.</span>'),
('alt="Gestión Hotelera en computadora"', 'alt="Hotel Management on desktop"', 'alt="Gestão Hoteleira no computador"'),
('alt="Bitácora: despachos del puerto en celular"', 'alt="Bitácora: port dispatches on mobile"', 'alt="Bitácora: despachos do porto no celular"'),
('alt="Gestión Hotelera: tablero de ventas en celular"', 'alt="Hotel Management: sales dashboard on mobile"', 'alt="Gestão Hoteleira: painel de vendas no celular"'),
# --- proyectos ---
('<p class="kicker">Proyectos</p><h2 id="workT">Trabajo <b>en producción</b></h2>', '<p class="kicker">Projects</p><h2 id="workT">Work <b>in production</b></h2>', '<p class="kicker">Projetos</p><h2 id="workT">Trabalho <b>em produção</b></h2>'),
('Apps web en uso todos los días, en la compu y en el celular. Sistemas internos de clientes y plataformas abiertas al público.', 'Web apps used every day, on desktop and mobile. Internal client systems and public platforms.', 'Apps web em uso todos os dias, no computador e no celular. Sistemas internos de clientes e plataformas abertas ao público.'),
('Deslizá para recorrer los proyectos', 'Scroll to browse the projects', 'Role para ver os projetos'),
("sector: 'Puerto · Practicaje y Pilotaje Buenos Aires', desc: 'De punta a punta: guardia, operación y facturación sincronizadas, sin papel.', url: 'Plataforma Bitácora · acceso privado'",
 "sector: 'Port · Practicaje y Pilotaje Buenos Aires', desc: 'End to end: dispatch desk, operations and billing in sync, paperless.', url: 'Bitácora platform · private access'",
 "sector: 'Porto · Practicaje y Pilotaje Buenos Aires', desc: 'De ponta a ponta: plantão, operação e faturamento sincronizados, sem papel.', url: 'Plataforma Bitácora · acesso privado'"),
("name: 'Administración integral', sector: 'Hospital El Cruce', desc: 'Contabilidad, finanzas, patrimonio, cuentas a pagar, impuestos y RRHH en uno.', url: 'Tablero de Gestión HEC · acceso privado'",
 "name: 'Integrated administration', sector: 'Hospital El Cruce', desc: 'Accounting, finance, fixed assets, accounts payable, taxes and HR in one.', url: 'HEC Management Dashboard · private access'",
 "name: 'Gestão integrada', sector: 'Hospital El Cruce', desc: 'Contabilidade, finanças, patrimônio, contas a pagar, impostos e RH em um só.', url: 'Painel de Gestão HEC · acesso privado'"),
("name: 'Gestión hotelera', sector: 'Marriott · Sheraton · City Express Plus', desc: 'Marriott Buenos Aires, Sheraton Bariloche, Sheraton Mar del Plata y City Express Plus by Marriott Buenos Aires.'",
 "name: 'Hotel management', sector: 'Marriott · Sheraton · City Express Plus', desc: 'Marriott Buenos Aires, Sheraton Bariloche, Sheraton Mar del Plata and City Express Plus by Marriott Buenos Aires.'",
 "name: 'Gestão hoteleira', sector: 'Marriott · Sheraton · City Express Plus', desc: 'Marriott Buenos Aires, Sheraton Bariloche, Sheraton Mar del Plata e City Express Plus by Marriott Buenos Aires.'"),
("name: 'Atlas Político Mundial', sector: 'Datos y cartografía', desc: '197 países: sistemas electorales, gobiernos e indicadores comparados.'",
 "name: 'World Political Atlas', sector: 'Data &amp; mapping', desc: '197 countries: electoral systems, governments and comparative indicators.'",
 "name: 'Atlas Político Mundial', sector: 'Dados e cartografia', desc: '197 países: sistemas eleitorais, governos e indicadores comparados.'"),
("name: 'Panel de gestión ABSA', sector: 'Aguas Bonaerenses', desc: 'Panel administrativo con acceso corporativo, reportes y seguimiento.', url: 'Panel ABSA · acceso privado'",
 "name: 'ABSA management panel', sector: 'Aguas Bonaerenses', desc: 'Administrative panel with corporate access, reports and tracking.', url: 'ABSA Panel · private access'",
 "name: 'Painel de gestão ABSA', sector: 'Aguas Bonaerenses', desc: 'Painel administrativo com acesso corporativo, relatórios e acompanhamento.', url: 'Painel ABSA · acesso privado'"),
("desc: 'Sistema de Gestión Administrativa: consolidado, cuotas, proveedores, saldos y resultados por línea, con un asistente de IA.', url: 'GGA SOFSA · acceso privado'",
 "desc: 'Administrative Management System: consolidated view, budget quotas, suppliers, balances and results by line, with an AI assistant.', url: 'GGA SOFSA · private access'",
 "desc: 'Sistema de Gestão Administrativa: consolidado, cotas, fornecedores, saldos e resultados por linha, com um assistente de IA.', url: 'GGA SOFSA · acesso privado'"),
("desc: 'Sistema de control interno con indicadores para decidir a tiempo.'", "desc: 'Internal control system with KPIs for timely decisions.'", "desc: 'Sistema de controle interno com indicadores para decidir a tempo.'"),
("sector: 'Empleo regional', desc: 'Bolsa de empleo con análisis de CV por inteligencia artificial.'", "sector: 'Regional employment', desc: 'Job board with AI-powered résumé analysis.'", "sector: 'Emprego regional', desc: 'Banco de empregos com análise de currículo por inteligência artificial.'"),
("name: 'Presupuesto plurianual', sector: 'Trenes Argentinos Operaciones', desc: 'Red interactiva del presupuesto 2027 y plurianual 2027–2029, con exportación a Excel, PDF y PowerPoint.', url: 'Presupuesto 2027 · acceso privado'",
 "name: 'Multi-year budget', sector: 'Trenes Argentinos Operaciones', desc: 'Interactive map of the 2027 budget and the 2027–2029 multi-year plan, with Excel, PDF and PowerPoint export.', url: 'Budget 2027 · private access'",
 "name: 'Orçamento plurianual', sector: 'Trenes Argentinos Operaciones', desc: 'Rede interativa do orçamento 2027 e do plurianual 2027–2029, com exportação para Excel, PDF e PowerPoint.', url: 'Orçamento 2027 · acesso privado'"),
("' en computadora\" loading", "' on desktop\" loading", "' no computador\" loading"),
("' en celular\" loading", "' on mobile\" loading", "' no celular\" loading"),
("'Acceso con usuario</span>'", "'User login required</span>'", "'Acesso com usuário</span>'"),
("'Sistema de uso interno</span>'", "'Internal system</span>'", "'Sistema de uso interno</span>'"),
("mobSvg + 'Celular</span>'", "mobSvg + 'Mobile</span>'", "mobSvg + 'Celular</span>'"),
# --- nosotros ---
('alt="Equipo trabajando frente a computadoras"', 'alt="Team working at computers"', 'alt="Equipe trabalhando em computadores"'),
('<p class="kicker">Por qué LTELC</p>', '<p class="kicker">Why LTELC</p>', '<p class="kicker">Por que LTELC</p>'),
('<blockquote>"Eso no está escrito en ningún lado, <b>lo tengo en la cabeza</b>."</blockquote>', '<blockquote>“That isn’t written down anywhere, <b>I have it in my head</b>.”</blockquote>', '<blockquote>“Isso não está escrito em lugar nenhum, <b>está na minha cabeça</b>.”</blockquote>'),
('<p>Es la frase que escuchamos en casi todas las organizaciones: cómo se arma el cierre del mes, qué proveedor se paga primero, de dónde sale tal indicador. Todo funciona, pero depende de que alguien lo recuerde.</p>',
 '<p>It’s what we hear in almost every organization: how the month-end close is done, which supplier gets paid first, where a KPI comes from. Everything works, but it depends on someone remembering it.</p>',
 '<p>É a frase que ouvimos em quase todas as organizações: como se faz o fechamento do mês, qual fornecedor se paga primeiro, de onde sai tal indicador. Tudo funciona, mas depende de alguém lembrar.</p>'),
('<p>Nuestro trabajo es convertir ese conocimiento en un sistema que funcione igual aunque esa persona esté de vacaciones.</p>',
 '<p>Our job is to turn that knowledge into a system that works just as well while that person is on vacation.</p>',
 '<p>Nosso trabalho é transformar esse conhecimento em um sistema que funcione igual mesmo quando essa pessoa está de férias.</p>'),
('>Hablemos de tu proyecto<', '>Let’s talk about your project<', '>Vamos falar do seu projeto<'),
# --- equipo ---
('<p class="kicker">Equipo</p><h2 id="teamT">Operación, finanzas y tecnología <b>en la misma mesa</b></h2>', '<p class="kicker">Team</p><h2 id="teamT">Operations, finance and technology <b>at the same table</b></h2>', '<p class="kicker">Equipe</p><h2 id="teamT">Operação, finanças e tecnologia <b>na mesma mesa</b></h2>'),
('Un equipo compacto que entiende cómo funciona una organización por dentro y lo convierte en sistemas que se usan todos los días.', 'A compact team that understands how an organization works from the inside and turns it into systems people use every day.', 'Uma equipe enxuta que entende como uma organização funciona por dentro e transforma isso em sistemas usados todos os dias.'),
('aria-label="LinkedIn de ', 'aria-label="LinkedIn: ', 'aria-label="LinkedIn de '),
('<div class="role">Socio fundador · Asuntos Públicos y Transformación Digital</div><div class="intl">Founding Partner · Public Affairs &amp; GovTech</div>',
 '<div class="role">Founding Partner · Public Affairs &amp; Digital Transformation</div><div class="intl">Public sector · Civil society · GovTech</div>',
 '<div class="role">Sócio fundador · Assuntos Públicos e Transformação Digital</div><div class="intl">Founding Partner · Public Affairs &amp; GovTech</div>'),
('<h3>Mg. Emmanuel Miranda</h3>', '<h3>Emmanuel Miranda</h3>', '<h3>Emmanuel Miranda</h3>'),
('<div class="role">Socio · Finanzas y Contabilidad</div><div class="intl">Partner · Finance &amp; Accounting — Media, Telecom &amp; Transport</div>',
 '<div class="role">Partner · Finance &amp; Accounting</div><div class="intl">Media, Telecom &amp; Transport</div>',
 '<div class="role">Sócio · Finanças e Contabilidade</div><div class="intl">Partner · Finance &amp; Accounting — Media, Telecom &amp; Transport</div>'),
('<h3>Lic. Fabián R. Álvarez</h3>', '<h3>Fabián R. Álvarez</h3>', '<h3>Fabián R. Álvarez</h3>'),
('<div class="role">Socio · Control de Gestión y Finanzas</div><div class="intl">Partner · Financial Control &amp; Reporting — Energy</div>',
 '<div class="role">Partner · Financial Control &amp; Reporting</div><div class="intl">Energy sector</div>',
 '<div class="role">Sócio · Controladoria e Finanças</div><div class="intl">Partner · Financial Control &amp; Reporting — Energy</div>'),
('<h3>Lic. Gustavo A. Maida</h3>', '<h3>Gustavo A. Maida</h3>', '<h3>Gustavo A. Maida</h3>'),
('<p class="cred">Mg. en Estudios Electorales (UNSAM) · Lic. en Ciencia Política (UCA)</p>', '<p class="cred">MA in Electoral Studies (UNSAM) · BA in Political Science (UCA)</p>', '<p class="cred">Mestre em Estudos Eleitorais (UNSAM) · Bacharel em Ciência Política (UCA)</p>'),
('<p>Más de 10 años en control de gestión y presupuesto en organizaciones públicas y privadas. Une la operación con la administración en sistemas, tableros e IA aplicada. También diseña campañas electorales y encuestas.</p>',
 '<p>10+ years in management control and budgeting in public and private organizations. Connects operations and administration through systems, dashboards and applied AI. Also designs electoral campaigns and polls.</p>',
 '<p>Mais de 10 anos em controladoria e orçamento em organizações públicas e privadas. Une a operação com a administração em sistemas, painéis e IA aplicada. Também desenha campanhas eleitorais e pesquisas.</p>'),
('<span>Asuntos públicos</span><span>Control de gestión</span><span>Business Intelligence</span><span>Sociedad civil</span>', '<span>Public affairs</span><span>Management control</span><span>Business Intelligence</span><span>Civil society</span>', '<span>Assuntos públicos</span><span>Controladoria</span><span>Business Intelligence</span><span>Sociedade civil</span>'),
('<span>IA generativa</span></div>', '<span>Generative AI</span></div>', '<span>IA generativa</span></div>'),
('<p>Experto en contabilidad, conciliaciones e impuestos para empresas y pymes de los sectores de comunicación y transporte. Implementa y opera sistemas ERP y CRM, y se asegura de que cada sistema cierre con los números.</p>',
 '<p>Expert in accounting, reconciliations and taxes for companies and SMEs in the media, telecom and transport sectors. Implements and runs ERP and CRM systems, and makes sure every system adds up.</p>',
 '<p>Especialista em contabilidade, conciliações e impostos para empresas e PMEs dos setores de comunicação e transporte. Implanta e opera sistemas ERP e CRM, e garante que cada sistema feche com os números.</p>'),
('<span>Contabilidad</span><span>Conciliaciones</span><span>Impuestos</span><span>Cierres contables</span>', '<span>Accounting</span><span>Reconciliations</span><span>Taxes</span><span>Month-end close</span>', '<span>Contabilidade</span><span>Conciliações</span><span>Impostos</span><span>Fechamento contábil</span>'),
('<span>Excel avanzado</span></div>', '<span>Advanced Excel</span></div>', '<span>Excel avançado</span></div>'),
('<p>Experto en conciliaciones, circuitos administrativos y control de gestión para empresas del sector energético. Trabaja sobre ERP y CRM y convierte la información en reportes y tableros que ayudan a decidir.</p>',
 '<p>Expert in reconciliations, administrative workflows and management control for energy-sector companies. Works across ERP and CRM and turns information into reports and dashboards that support decisions.</p>',
 '<p>Especialista em conciliações, fluxos administrativos e controladoria para empresas do setor de energia. Trabalha com ERP e CRM e transforma a informação em relatórios e painéis que ajudam a decidir.</p>'),
('<span>Conciliaciones</span><span>Control de gestión</span><span>Circuitos administrativos</span><span>Reporting</span>', '<span>Reconciliations</span><span>Management control</span><span>Administrative workflows</span><span>Reporting</span>', '<span>Conciliações</span><span>Controladoria</span><span>Fluxos administrativos</span><span>Reporting</span>'),
('<div class="mlabel">Especialidades</div>', '<div class="mlabel">Expertise</div>', '<div class="mlabel">Especialidades</div>'),
('<div class="mlabel">Herramientas</div>', '<div class="mlabel">Tools</div>', '<div class="mlabel">Ferramentas</div>'),
('<div><h4>Cubrimos</h4><p>Operación, administración, contabilidad, finanzas, control de gestión, datos y desarrollo.</p></div>', '<div><h4>We cover</h4><p>Operations, administration, accounting, finance, management control, data and development.</p></div>', '<div><h4>Cobrimos</h4><p>Operação, administração, contabilidade, finanças, controladoria, dados e desenvolvimento.</p></div>'),
('<div><h4>Sectores</h4><p>Sector público, sociedad civil, transporte, comunicación, energía, salud, hotelería y puertos.</p></div>', '<div><h4>Industries</h4><p>Public sector, civil society, transport, media &amp; telecom, energy, healthcare, hospitality and ports.</p></div>', '<div><h4>Setores</h4><p>Setor público, sociedade civil, transporte, comunicação, energia, saúde, hotelaria e portos.</p></div>'),
('<div><h4>Herramientas</h4><p>Power BI, SQL, Python, SAP, Tango, CRM, Excel avanzado e IA generativa.</p></div>', '<div><h4>Tools</h4><p>Power BI, SQL, Python, SAP, Tango, CRM, advanced Excel and generative AI.</p></div>', '<div><h4>Ferramentas</h4><p>Power BI, SQL, Python, SAP, Tango, CRM, Excel avançado e IA generativa.</p></div>'),
# --- proceso ---
('<p class="kicker">Cómo trabajamos</p><h2 id="procT">De la charla a algo <b>que funciona</b></h2>', '<p class="kicker">How we work</p><h2 id="procT">From a conversation to something <b>that works</b></h2>', '<p class="kicker">Como trabalhamos</p><h2 id="procT">Da conversa a algo <b>que funciona</b></h2>'),
('Entregas cortas y visibles. Ves avances reales desde las primeras semanas y decidimos juntos el paso siguiente.', 'Short, visible deliveries. You see real progress from the first weeks and we decide the next step together.', 'Entregas curtas e visíveis. Você vê avanços reais desde as primeiras semanas e decidimos juntos o próximo passo.'),
('aria-label="Etapas"', 'aria-label="Stages"', 'aria-label="Etapas"'),
('<span class="n">01</span>Diagnóstico<', '<span class="n">01</span>Assessment<', '<span class="n">01</span>Diagnóstico<'),
('<span class="n">02</span>Propuesta<', '<span class="n">02</span>Proposal<', '<span class="n">02</span>Proposta<'),
('<span class="n">03</span>Desarrollo<', '<span class="n">03</span>Development<', '<span class="n">03</span>Desenvolvimento<'),
('<span class="n">04</span>Puesta en marcha<', '<span class="n">04</span>Go-live<', '<span class="n">04</span>Implantação<'),
('<h3 id="tpH">Diagnóstico</h3>', '<h3 id="tpH">Assessment</h3>', '<h3 id="tpH">Diagnóstico</h3>'),
('<p id="tpP">Relevamos cómo trabajás hoy, qué sistemas y planillas usás y dónde se pierde el tiempo. La primera reunión no tiene costo.</p>', '<p id="tpP">We map how you work today, which systems and spreadsheets you use and where time gets lost. The first meeting is free.</p>', '<p id="tpP">Levantamos como você trabalha hoje, quais sistemas e planilhas usa e onde o tempo se perde. A primeira reunião não tem custo.</p>'),
('<ul id="tpL"><li>Entrevistas con las áreas clave</li><li>Mapa de procesos y de datos</li><li>Prioridades por impacto</li></ul>', '<ul id="tpL"><li>Interviews with key areas</li><li>Process and data map</li><li>Priorities by impact</li></ul>', '<ul id="tpL"><li>Entrevistas com as áreas-chave</li><li>Mapa de processos e de dados</li><li>Prioridades por impacto</li></ul>'),
("{ h: 'Diagnóstico', p: 'Relevamos cómo trabajás hoy, qué sistemas y planillas usás y dónde se pierde el tiempo. La primera reunión no tiene costo.', l: ['Entrevistas con las áreas clave', 'Mapa de procesos y de datos', 'Prioridades por impacto'] }",
 "{ h: 'Assessment', p: 'We map how you work today, which systems and spreadsheets you use and where time gets lost. The first meeting is free.', l: ['Interviews with key areas', 'Process and data map', 'Priorities by impact'] }",
 "{ h: 'Diagnóstico', p: 'Levantamos como você trabalha hoje, quais sistemas e planilhas usa e onde o tempo se perde. A primeira reunião não tem custo.', l: ['Entrevistas com as áreas-chave', 'Mapa de processos e de dados', 'Prioridades por impacto'] }"),
("{ h: 'Propuesta', p: 'Definimos alcance, etapas, plazos y presupuesto cerrado por etapa, por escrito y sin letra chica.', l: ['Alcance por módulos', 'Plan de entregas', 'Presupuesto por etapa'] }",
 "{ h: 'Proposal', p: 'We define scope, stages, timelines and a fixed price per stage, in writing and with no fine print.', l: ['Scope by module', 'Delivery plan', 'Price per stage'] }",
 "{ h: 'Proposta', p: 'Definimos escopo, etapas, prazos e orçamento fechado por etapa, por escrito e sem letras miúdas.', l: ['Escopo por módulos', 'Plano de entregas', 'Orçamento por etapa'] }"),
("{ h: 'Desarrollo', p: 'Construimos en etapas cortas, con una versión de prueba que tu equipo usa desde las primeras semanas.', l: ['Avances semanales visibles', 'Pruebas con datos reales', 'Ajustes sobre la marcha'] }",
 "{ h: 'Development', p: 'We build in short stages, with a test version your team uses from the first weeks.', l: ['Visible weekly progress', 'Testing with real data', 'Adjustments along the way'] }",
 "{ h: 'Desenvolvimento', p: 'Construímos em etapas curtas, com uma versão de teste que sua equipe usa desde as primeiras semanas.', l: ['Avanços semanais visíveis', 'Testes com dados reais', 'Ajustes ao longo do caminho'] }"),
("{ h: 'Puesta en marcha', p: 'Implementamos, migramos datos, capacitamos al equipo y quedamos para soporte y mejoras continuas.', l: ['Migración de datos', 'Capacitación por área', 'Soporte y evolución'] }",
 "{ h: 'Go-live', p: 'We deploy, migrate data, train the team and stay on for support and continuous improvement.', l: ['Data migration', 'Training by area', 'Support and evolution'] }",
 "{ h: 'Implantação', p: 'Implantamos, migramos dados, capacitamos a equipe e seguimos com suporte e melhorias contínuas.', l: ['Migração de dados', 'Capacitação por área', 'Suporte e evolução'] }"),
# --- contacto ---
('<p class="kicker" style="color:#7FA6FF">Contacto</p>', '<p class="kicker" style="color:#7FA6FF">Contact</p>', '<p class="kicker" style="color:#7FA6FF">Contato</p>'),
('<h2 id="contactT">¿Qué tenés <b>en la cabeza?</b></h2>', '<h2 id="contactT">What do you have <b>in your head?</b></h2>', '<h2 id="contactT">O que você tem <b>na cabeça?</b></h2>'),
('Contanos qué querés ordenar, automatizar o medir. Respondemos en el día hábil.', 'Tell us what you want to organize, automate or measure. We reply within one business day.', 'Conte o que você quer organizar, automatizar ou medir. Respondemos no mesmo dia útil.'),
('<label for="f-name">Nombre</label>', '<label for="f-name">Name</label>', '<label for="f-name">Nome</label>'),
('<label for="f-org">Organización</label>', '<label for="f-org">Organization</label>', '<label for="f-org">Organização</label>'),
('<label for="f-topic">¿Qué necesitás?</label>', '<label for="f-topic">What do you need?</label>', '<label for="f-topic">Do que você precisa?</label>'),
('<option>Sistema de administración</option><option>Balances y contabilidad</option><option>Tablero de indicadores</option><option>Software o app a medida</option><option>Automatización o integración</option><option>Inteligencia artificial</option><option>Quiero asesoramiento</option>',
 '<option>Management system</option><option>Accounting &amp; financial statements</option><option>KPI dashboard</option><option>Custom software or app</option><option>Automation or integration</option><option>Artificial intelligence</option><option>I need advice</option>',
 '<option>Sistema de gestão</option><option>Contabilidade e balanços</option><option>Painel de indicadores</option><option>Software ou app sob medida</option><option>Automação ou integração</option><option>Inteligência artificial</option><option>Quero uma assessoria</option>'),
('<label for="f-msg">Contanos brevemente</label>', '<label for="f-msg">Tell us briefly</label>', '<label for="f-msg">Conte brevemente</label>'),
('placeholder="Ej.: llevamos la administración en tres planillas y el balance lo armamos a mano cada mes."', 'placeholder="E.g. we run administration on three spreadsheets and build the financial statements by hand every month."', 'placeholder="Ex.: fazemos a administração em três planilhas e montamos o balanço à mão todo mês."'),
('>Enviar por WhatsApp<', '>Send via WhatsApp<', '>Enviar pelo WhatsApp<'),
('>Enviar por email<', '>Send by email<', '>Enviar por e-mail<'),
("'Completá tu nombre y una breve descripción para poder responderte.'", "'Please add your name and a short description so we can reply.'", "'Preencha seu nome e uma breve descrição para podermos responder.'"),
("'Hola LTELC, soy '", "'Hi LTELC, I am '", "'Olá LTELC, sou '"),
("'.\\nTema: '", "'.\\nTopic: '", "'.\\nAssunto: '"),
("encodeURIComponent('Consulta: ' + t)", "encodeURIComponent('Inquiry: ' + t)", "encodeURIComponent('Consulta: ' + t)"),
("'Abrimos tu correo con el mensaje listo. Si no se abrió, escribinos a '", "'Your email app opened with the message ready. If it didn’t, write to us at '", "'Abrimos seu e-mail com a mensagem pronta. Se não abriu, escreva para '"),
("'Abrimos WhatsApp con el mensaje listo. Si no se abrió, escribinos al '", "'WhatsApp opened with the message ready. If it didn’t, message us at '", "'Abrimos o WhatsApp com a mensagem pronta. Se não abriu, escreva para '"),
# --- pie ---
('<p>Lo Tengo en la Cabeza. Consultora en sistemas de gestión, datos y software a medida.</p>', '<p>Lo Tengo en la Cabeza. Consultancy in management systems, data and custom software.</p>', '<p>Lo Tengo en la Cabeza. Consultoria em sistemas de gestão, dados e software sob medida.</p>'),
('<h4>Soluciones</h4><ul><li><a href="#soluciones">Administración</a></li><li><a href="#soluciones">Balances</a></li><li><a href="#soluciones">Indicadores</a></li><li><a href="#soluciones">Software a medida</a></li><li><a href="#soluciones">Automatización e IA</a></li></ul>',
 '<h4>Solutions</h4><ul><li><a href="#soluciones">Management</a></li><li><a href="#soluciones">Accounting</a></li><li><a href="#soluciones">KPIs</a></li><li><a href="#soluciones">Custom software</a></li><li><a href="#soluciones">Automation &amp; AI</a></li></ul>',
 '<h4>Soluções</h4><ul><li><a href="#soluciones">Gestão</a></li><li><a href="#soluciones">Balanços</a></li><li><a href="#soluciones">Indicadores</a></li><li><a href="#soluciones">Software sob medida</a></li><li><a href="#soluciones">Automação e IA</a></li></ul>'),
('<h4>Sectores</h4><ul><li><a href="#sectores">Transporte</a></li><li><a href="#sectores">Puertos</a></li><li><a href="#sectores">Salud</a></li><li><a href="#sectores">Hotelería</a></li><li><a href="#sectores">Agua y saneamiento</a></li></ul>',
 '<h4>Industries</h4><ul><li><a href="#sectores">Transport</a></li><li><a href="#sectores">Ports</a></li><li><a href="#sectores">Healthcare</a></li><li><a href="#sectores">Hospitality</a></li><li><a href="#sectores">Water &amp; sanitation</a></li></ul>',
 '<h4>Setores</h4><ul><li><a href="#sectores">Transporte</a></li><li><a href="#sectores">Portos</a></li><li><a href="#sectores">Saúde</a></li><li><a href="#sectores">Hotelaria</a></li><li><a href="#sectores">Água e saneamento</a></li></ul>'),
('<h4>Contacto</h4>', '<h4>Contact</h4>', '<h4>Contato</h4>'),
('>Formulario<', '>Contact form<', '>Formulário<'),
('<small>¿Ya sos cliente?</small><strong>Soporte y reclamos</strong>', '<small>Already a client?</small><strong>Support &amp; tickets</strong>', '<small>Já é cliente?</small><strong>Suporte e chamados</strong>'),
('>Soporte a clientes<', '>Client support<', '>Suporte a clientes<'),
]

LANGS = {
    'en': {'idx': 1, 'html': 'en', 'og': 'en_US'},
    'pt': {'idx': 2, 'html': 'pt-BR', 'og': 'pt_BR'},
}
SWITCH_ES = '<a href="./" class="on" lang="es" hreflang="es">ES</a><a href="en/" lang="en" hreflang="en">EN</a><a href="pt/" lang="pt" hreflang="pt">PT</a>'

def build(code, cfg):
    s = open(SRC, encoding='utf-8').read()
    missing = [row[0][:70] for row in T if row[0] not in s]
    if missing:
        sys.exit('No encontrado en index.html:\n  ' + '\n  '.join(missing))
    for row in T:
        s = s.replace(row[0], row[cfg['idx']])
    # rutas relativas desde la subcarpeta
    s = s.replace('src="img/', 'src="../img/').replace("'img/", "'../img/").replace('href="favicon.svg"', 'href="../favicon.svg"')
    s = s.replace('<html lang="es-AR">', '<html lang="%s">' % cfg['html'])
    s = s.replace('<link rel="canonical" href="https://ltelc.tech/">', '<link rel="canonical" href="https://ltelc.tech/%s/">' % code)
    s = s.replace('<meta property="og:url" content="https://ltelc.tech/">', '<meta property="og:url" content="https://ltelc.tech/%s/">' % code)
    s = s.replace('<meta property="og:locale" content="es_AR">', '<meta property="og:locale" content="%s">' % cfg['og'])
    sw = '<a href="../" lang="es" hreflang="es">ES</a><a href="../en/"%s lang="en" hreflang="en">EN</a><a href="../pt/"%s lang="pt" hreflang="pt">PT</a>' % (
        ' class="on"' if code == 'en' else '', ' class="on"' if code == 'pt' else '')
    assert SWITCH_ES in s
    s = s.replace(SWITCH_ES, sw)
    out = os.path.join(ROOT, code, 'index.html')
    os.makedirs(os.path.dirname(out), exist_ok=True)
    open(out, 'w', encoding='utf-8').write(s)
    print('ok', out)

if __name__ == '__main__':
    for code, cfg in LANGS.items():
        build(code, cfg)
