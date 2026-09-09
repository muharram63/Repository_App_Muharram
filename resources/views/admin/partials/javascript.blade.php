<script>
    /* ---------------- Design tokens ---------------- */
    const C = {
        blue:"#1656D6", blueSoft:"#2F6FEF", ink600:"#48546B", ink400:"#8592A6",
        line:"#E4E9F2", green:"#12946F", amber:"#C98A12"
    };
    const PIE_COLORS = [C.blue, C.blueSoft, "#6FA0F5", "#9CBEF7", C.amber, C.ink400];

    /* ---------------- Mock data ---------------- */
    const vacancies = [
       ];
    const users = [
        { id:"U-8831", name:"Дарья Соколова", role:"Соискатель", email:"d.sokolova@mail.ru", status:"active", joined:"12 мая" },
        { id:"U-8830", name:"ООО «Технопарк ИТ»", role:"Работодатель", email:"hr@technopark.ru", status:"active", joined:"10 мая" },
        { id:"U-8829", name:"Игорь Титов", role:"Соискатель", email:"titov.igor@yandex.ru", status:"blocked", joined:"3 мая" },
        { id:"U-8828", name:"Мария Ким", role:"Соискатель", email:"m.kim@gmail.com", status:"active", joined:"1 мая" },
        { id:"U-8827", name:"ООО «БыстроДоставка»", role:"Работодатель", email:"hr@bystro.ru", status:"pending", joined:"28 апр" },
    ];
    const companies = [
        { id:"C-221", name:"Технопарк ИТ", industry:"IT / Разработка", vacanciesOpen:12, verified:true, complaints:0 },
        { id:"C-220", name:"СтройГрупп", industry:"Строительство", vacanciesOpen:5, verified:true, complaints:1 },
        { id:"C-219", name:"БыстроДоставка", industry:"Логистика", vacanciesOpen:3, verified:false, complaints:4 },
        { id:"C-218", name:"МедиаХолдинг", industry:"Медиа", vacanciesOpen:8, verified:true, complaints:0 },
    ];
    const resumes = [
        { id:"R-5510", name:"Дарья Соколова", role:"Frontend-разработчик", updated:"29 июн", visibility:"public" },
        { id:"R-5509", name:"Игорь Титов", role:"Логист", updated:"27 июн", visibility:"hidden" },
        { id:"R-5508", name:"Мария Ким", role:"SMM-специалист", updated:"26 июн", visibility:"public" },
        { id:"R-5507", name:"Павел Орлов", role:"Data Scientist", updated:"24 июн", visibility:"public" },
    ];
    const complaints = [
        { id:"X-301", type:"Вакансия", target:"Курьер на авто — БыстроДоставка", reason:"Похоже на мошенничество", status:"open" },
        { id:"X-300", type:"Компания", target:"БыстроДоставка", reason:"Не выплачивают зарплату", status:"open" },
        { id:"X-299", type:"Резюме", target:"R-5502", reason:"Фейковый профиль", status:"resolved" },
        { id:"X-298", type:"Пользователь", target:"U-8790", reason:"Спам в сообщениях", status:"resolved" },
    ];
    const regTrend = [
        { m:"Янв", соискатели:1200, работодатели:180 },
        { m:"Фев", соискатели:1400, работодатели:210 },
        { m:"Мар", соискатели:1650, работодатели:240 },
        { m:"Апр", соискатели:1500, работодатели:260 },
        { m:"Май", соискатели:1900, работодатели:300 },
        { m:"Июн", соискатели:2200, работодатели:340 },
    ];
    const categoryData = [
        { name:"IT", value:32 }, { name:"Продажи", value:22 }, { name:"Логистика", value:16 },
        { name:"Маркетинг", value:12 }, { name:"Производство", value:10 }, { name:"Другое", value:8 },
    ];

    const badgeLabels = {
        pending:"На модерации", approved:"Одобрено", rejected:"Отклонено",
        active:"Активен", blocked:"Заблокирован", open:"Открыта", resolved:"Решена",
        public:"Видно всем", hidden:"Скрыто"
    };
    const badge = (status) => `<span class="badge ${status}">${badgeLabels[status]}</span>`;

    /* ---------------- Navigation ---------------- */
    const NAV = [
        { key:"dashboard", label:"Обзор", icon:"fa-gauge-high" },
        { key:"vacancies", label:"Вакансии", icon:"fa-briefcase", badge: vacancies.filter(v=>v.status==="pending").length },
        { key:"users", label:"Пользователи", icon:"fa-users" },
        { key:"companies", label:"Компании", icon:"fa-building" },
        { key:"resumes", label:"Резюме", icon:"fa-file-lines" },
        { key:"analytics", label:"Аналитика", icon:"fa-chart-simple" },
        { key:"complaints", label:"Жалобы", icon:"fa-flag", badge: complaints.filter(c=>c.status==="open").length },
    ];

    const navEl = document.getElementById("nav");
    NAV.forEach(item => {
        const btn = document.createElement("button");
        btn.className = "nav-item" + (item.key==="dashboard" ? " active" : "");
        btn.dataset.key = item.key;
        btn.innerHTML = `<i class="fa-solid ${item.icon}"></i><span class="label">${item.label}</span>` +
            (item.badge ? `<span class="nav-badge">${item.badge}</span>` : "");
        btn.onclick = () => switchPage(item.key);
        navEl.appendChild(btn);
    });

    function switchPage(key){
        document.querySelectorAll(".nav-item").forEach(b => b.classList.toggle("active", b.dataset.key === key));
        document.querySelectorAll(".page").forEach(p => p.classList.toggle("active", p.id === "page-"+key));
        document.getElementById("crumb").textContent = NAV.find(n=>n.key===key).label;
    }

    /* ---------------- Dashboard ---------------- */
    function kpiCard(label, value, delta, up, icon){
        return `<div class="card kpi">
    <div class="kpi-top">
      <div><div class="kpi-label">${label}</div><div class="kpi-value disp">${value}</div></div>
      <div class="kpi-icon"><i class="fa-solid ${icon}"></i></div>
    </div>
    <div class="kpi-delta ${up?'up':'down'}"><i class="fa-solid fa-arrow-trend-${up?'up':'down'}"></i> ${delta}<span>&nbsp;за месяц</span></div>
  </div>`;
    }
    document.getElementById("kpi-row").innerHTML =
        kpiCard("Активные вакансии","4 812","+6.2%",true,"fa-briefcase") +
        kpiCard("Новые пользователи","1 034","+12.4%",true,"fa-users") +
        kpiCard("Резюме обновлено","642","+3.1%",true,"fa-file-lines") +
        kpiCard("Ожидают модерации","27","-4.0%",false,"fa-shield-halved");

    document.getElementById("dash-queue").innerHTML = vacancies.filter(v=>v.status==="pending").map(v => `
  <tr><td class="muted">${v.id}</td><td class="bold">${v.title}</td><td>${v.company}</td><td>${badge(v.status)}</td><td class="muted">${v.date}</td></tr>
`).join("");

    /* ---------------- Vacancies ---------------- */
    let vacFilter = "all", vacQuery = "";
    const vacFiltersEl = document.getElementById("vac-filters");
    const vacFilterDefs = [["all","Все"],["pending","На модерации"],["approved","Одобренные"],["rejected","Отклонённые"]];
    vacFiltersEl.innerHTML = vacFilterDefs.map(([k,l]) => `<button class="pill ${k===vacFilter?'active':''}" data-k="${k}">${l}</button>`).join("");
    vacFiltersEl.querySelectorAll(".pill").forEach(p => p.onclick = () => { vacFilter = p.dataset.k; renderVacFilters(); renderVacancies(); });
    function renderVacFilters(){ vacFiltersEl.querySelectorAll(".pill").forEach(p => p.classList.toggle("active", p.dataset.k===vacFilter)); }
    document.getElementById("vac-search").addEventListener("input", e => { vacQuery = e.target.value.toLowerCase(); renderVacancies(); });

    function renderVacancies(){
        const list = vacancies.filter(v =>
            (vacFilter==="all" || v.status===vacFilter) &&
            (v.title.toLowerCase().includes(vacQuery) || v.company.toLowerCase().includes(vacQuery))
        );
        document.getElementById("vac-sub").textContent = `${vacancies.length} всего · ${vacancies.filter(v=>v.status==="pending").length} ожидают модерации`;
        document.getElementById("vac-body").innerHTML = list.map(v => `
    <tr>
      <td class="bold">${v.title}</td><td>${v.company}</td><td class="sub">${v.city}</td><td class="sub">${v.salary}</td>
      <td>${badge(v.status)}</td><td class="muted">${v.date}</td>
      <td><div class="actions-cell">
        <button class="icon-btn" title="Просмотреть"><i class="fa-regular fa-eye"></i></button>
        ${v.status==="pending" ? `
          <button class="icon-btn green" title="Одобрить"><i class="fa-solid fa-check"></i></button>
          <button class="icon-btn red" title="Отклонить"><i class="fa-solid fa-xmark"></i></button>` : ""}
      </div></td>
    </tr>`).join("");
    }
    renderVacancies();

    /* ---------------- Users ---------------- */
    let userFilter = "all";
    const userFiltersEl = document.getElementById("user-filters");
    const userFilterDefs = [["all","Все"],["Соискатель","Соискатели"],["Работодатель","Работодатели"]];
    userFiltersEl.innerHTML = userFilterDefs.map(([k,l]) => `<button class="pill ${k===userFilter?'active':''}" data-k="${k}">${l}</button>`).join("");
    userFiltersEl.querySelectorAll(".pill").forEach(p => p.onclick = () => { userFilter = p.dataset.k; userFiltersEl.querySelectorAll(".pill").forEach(x=>x.classList.toggle("active", x.dataset.k===userFilter)); renderUsers(); });
    function renderUsers(){
        const list = users.filter(u => userFilter==="all" || u.role===userFilter);
        document.getElementById("user-body").innerHTML = list.map(u => `
    <tr>
      <td class="bold">${u.name}</td><td class="sub">${u.role}</td><td class="sub">${u.email}</td>
      <td>${badge(u.status)}</td><td class="muted">${u.joined}</td>
      <td><div class="actions-cell"><button class="icon-btn" title="Действия"><i class="fa-solid fa-ellipsis"></i></button></div></td>
    </tr>`).join("");
    }
    renderUsers();

    /* ---------------- Companies ---------------- */
    document.getElementById("company-grid").innerHTML = companies.map(c => `
  <div class="card company-card">
    <div class="top">
      <div><div class="company-name">${c.name}</div><div class="company-industry">${c.industry}</div></div>
      <span class="tag ${c.verified?'verified':'unverified'}">${c.verified?'Проверено':'Не проверено'}</span>
    </div>
    <div class="company-stats">
      <div><div class="stat-label">Вакансий</div><div class="stat-value">${c.vacanciesOpen}</div></div>
      <div><div class="stat-label">Жалоб</div><div class="stat-value ${c.complaints>0?'danger':''}">${c.complaints}</div></div>
    </div>
  </div>`).join("");

    /* ---------------- Resumes ---------------- */
    document.getElementById("resume-body").innerHTML = resumes.map(r => `
  <tr>
    <td class="bold">${r.name}</td><td class="sub">${r.role}</td><td class="muted">${r.updated}</td>
    <td>${badge(r.visibility)}</td>
    <td><div class="actions-cell"><button class="icon-btn" title="Просмотреть"><i class="fa-regular fa-eye"></i></button></div></td>
  </tr>`).join("");

    /* ---------------- Complaints ---------------- */
    let compFilter = "open";
    const compFiltersEl = document.getElementById("complaint-filters");
    const compFilterDefs = [["open","Открытые"],["resolved","Решённые"],["all","Все"]];
    compFiltersEl.innerHTML = compFilterDefs.map(([k,l]) => `<button class="pill ${k===compFilter?'active':''}" data-k="${k}">${l}</button>`).join("");
    compFiltersEl.querySelectorAll(".pill").forEach(p => p.onclick = () => { compFilter = p.dataset.k; compFiltersEl.querySelectorAll(".pill").forEach(x=>x.classList.toggle("active", x.dataset.k===compFilter)); renderComplaints(); });
    function renderComplaints(){
        const list = complaints.filter(c => compFilter==="all" || c.status===compFilter);
        document.getElementById("complaint-body").innerHTML = list.map(c => `
    <tr>
      <td class="muted">${c.id}</td><td class="bold">${c.type}</td><td class="sub">${c.target}</td><td class="sub">${c.reason}</td>
      <td>${badge(c.status)}</td>
      <td>${c.status==="open" ? `<div class="actions-cell">
          <button class="icon-btn green" title="Решить"><i class="fa-solid fa-check"></i></button>
          <button class="icon-btn red" title="Отклонить"><i class="fa-solid fa-xmark"></i></button>
        </div>` : ""}</td>
    </tr>`).join("");
    }
    renderComplaints();

    /* ---------------- Charts ---------------- */
    Chart.defaults.font.family = "Inter";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = C.ink400;

    new Chart(document.getElementById("chartReg"), {
        type: "line",
        data: {
            labels: regTrend.map(d=>d.m),
            datasets: [
                { label:"Соискатели", data:regTrend.map(d=>d.соискатели), borderColor:C.blue, backgroundColor:"rgba(22,86,214,0.15)", fill:true, tension:0.35, pointRadius:3 },
                { label:"Работодатели", data:regTrend.map(d=>d.работодатели), borderColor:C.amber, backgroundColor:"transparent", tension:0.35, pointRadius:2 },
            ]
        },
        options: { plugins:{legend:{position:"bottom", labels:{boxWidth:10, boxHeight:10}}}, scales:{ x:{grid:{display:false}}, y:{grid:{color:C.line}} } }
    });

    new Chart(document.getElementById("chartCat"), {
        type: "doughnut",
        data: { labels: categoryData.map(d=>d.name), datasets:[{ data: categoryData.map(d=>d.value), backgroundColor: PIE_COLORS, borderWidth:0 }] },
        options: { plugins:{legend:{position:"bottom", labels:{boxWidth:10, boxHeight:10}}}, cutout:"55%" }
    });

    new Chart(document.getElementById("chartCompanies"), {
        type:"bar",
        data:{ labels:regTrend.map(d=>d.m), datasets:[{ label:"Новые компании", data:regTrend.map(d=>d.работодатели), backgroundColor:C.blue, borderRadius:6 }] },
        options:{ plugins:{legend:{display:false}}, scales:{ x:{grid:{display:false}}, y:{grid:{color:C.line}} } }
    });

    new Chart(document.getElementById("chartGrowth"), {
        type:"line",
        data:{ labels:regTrend.map(d=>d.m), datasets:[{ label:"Соискатели", data:regTrend.map(d=>d.соискатели), borderColor:C.blueSoft, backgroundColor:"transparent", tension:0.35, pointRadius:3 }] },
        options:{ plugins:{legend:{display:false}}, scales:{ x:{grid:{display:false}}, y:{grid:{color:C.line}} } }
    });

    new Chart(document.getElementById("chartCatBar"), {
        type:"bar",
        data:{ labels:categoryData.map(d=>d.name), datasets:[{ data:categoryData.map(d=>d.value), backgroundColor:PIE_COLORS, borderRadius:6 }] },
        options:{ indexAxis:"y", plugins:{legend:{display:false}}, scales:{ x:{grid:{color:C.line}}, y:{grid:{display:false}} } }
    });
</script>
