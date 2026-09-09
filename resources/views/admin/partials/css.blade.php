<style>
    @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Inter:wght@400;500;600;700&display=swap');

    :root{
        --navy:#0B1B3A; --navy-soft:#122a54;
        --blue:#1656D6; --blue-soft:#2F6FEF; --sky:#EAF1FE;
        --bg:#F4F7FC; --ink-900:#101828; --ink-600:#48546B; --ink-400:#8592A6;
        --line:#E4E9F2;
        --green:#12946F; --green-bg:#E7F6F0;
        --amber:#C98A12; --amber-bg:#FBF1DE;
        --red:#D64545; --red-bg:#FBEAEA;
    }
    *{box-sizing:border-box; margin:0; padding:0; font-family:'Inter',sans-serif;}
    .disp{font-family:'Manrope',sans-serif;}
    body{background:var(--bg); color:var(--ink-900);}
    ::-webkit-scrollbar{width:8px; height:8px;}
    ::-webkit-scrollbar-thumb{background:#C9D4E6; border-radius:8px;}

    .app{display:flex; min-height:100vh;}

    /* Sidebar */
    .sidebar{width:250px; background:var(--navy); flex-shrink:0; display:flex; flex-direction:column; padding:20px 14px;}
    .brand{display:flex; align-items:center; gap:10px; padding:6px 10px 24px;}
    .brand-icon{width:32px; height:32px; border-radius:9px; background:var(--blue); display:grid; place-items:center; color:#fff; font-size:14px;}
    .brand-name{color:#fff; font-weight:800; font-size:16px;}
    .nav{display:flex; flex-direction:column; gap:3px;}
    .nav-item{display:flex; align-items:center; gap:11px; padding:10px 12px; border-radius:10px; border:none; cursor:pointer;
        text-align:left; font-size:14px; font-weight:600; background:transparent; color:#B9C6E6; width:100%;}
    .nav-item i{width:17px; text-align:center; font-size:15px;}
    .nav-item span.label{flex:1;}
    .nav-item.active{background:var(--blue); color:#fff;}
    .nav-badge{background:var(--blue-soft); color:#fff; font-size:11px; font-weight:700; padding:1px 7px; border-radius:999px;}
    .nav-item.active .nav-badge{background:rgba(255,255,255,0.25);}
    .status-box{margin-top:auto; padding:12px; background:var(--navy-soft); border-radius:12px;
        display:flex; align-items:center; gap:8px; color:#fff; font-size:12.5px; font-weight:600;}
    .status-box i{color:#4ADE80; font-size:10px;}

    /* Main */
    .main{flex:1; display:flex; flex-direction:column; min-width:0;}
    header{height:64px; background:#fff; border-bottom:1px solid var(--line); display:flex; align-items:center;
        justify-content:space-between; padding:0 24px; flex-shrink:0;}
    .breadcrumb{font-size:13.5px; color:var(--ink-400);}
    .breadcrumb b{color:var(--ink-900); font-weight:600;}
    .header-right{display:flex; align-items:center; gap:18px;}
    .bell{position:relative; background:none; border:none; cursor:pointer; color:var(--ink-600); font-size:18px;}
    .bell .dot{position:absolute; top:-2px; right:-2px; width:8px; height:8px; border-radius:999px; background:var(--red);}
    .profile{display:flex; align-items:center; gap:8px; cursor:pointer;}
    .avatar{width:32px; height:32px; border-radius:999px; background:var(--sky); color:var(--blue); display:grid;
        place-items:center; font-weight:700; font-size:13px;}
    .profile-name{font-size:13px; font-weight:600; color:var(--ink-900);}

    main{padding:24px; overflow-y:auto;}
    .page{display:none;}
    .page.active{display:block;}

    /* Section header */
    .section-header{display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:18px; flex-wrap:wrap; gap:12px;}
    .section-header h1{font-size:22px; font-weight:800; color:var(--ink-900);}
    .section-header .sub{font-size:13.5px; color:var(--ink-400); margin-top:4px;}

    /* Card */
    .card{background:#fff; border:1px solid var(--line); border-radius:16px; padding:20px; box-shadow:0 1px 2px rgba(16,24,40,0.04);}
    .card-title{font-weight:700; font-size:14px; color:var(--ink-900); margin-bottom:12px;}

    /* KPI */
    .kpi-row{display:flex; gap:16px; flex-wrap:wrap; margin-bottom:20px;}
    .kpi{flex:1; min-width:200px;}
    .kpi-top{display:flex; justify-content:space-between; align-items:flex-start;}
    .kpi-label{font-size:13px; color:var(--ink-400); font-weight:600; margin-bottom:8px;}
    .kpi-value{font-size:28px; font-weight:800; color:var(--ink-900);}
    .kpi-icon{width:38px; height:38px; border-radius:10px; background:var(--sky); display:grid; place-items:center; color:var(--blue);}
    .kpi-delta{display:flex; align-items:center; gap:4px; margin-top:10px; font-size:13px; font-weight:600;}
    .kpi-delta.up{color:var(--green);} .kpi-delta.down{color:var(--red);}
    .kpi-delta span{color:var(--ink-400); font-weight:500;}

    /* grid layouts */
    .grid-2{display:grid; grid-template-columns:1.6fr 1fr; gap:16px; margin-bottom:16px;}
    .grid-2-eq{display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;}
    .grid-cards{display:grid; grid-template-columns:repeat(auto-fill, minmax(260px,1fr)); gap:14px;}
    @media (max-width:900px){ .grid-2, .grid-2-eq{grid-template-columns:1fr;} }

    /* Search / filter */
    .search{display:flex; align-items:center; gap:8px; background:#fff; border:1px solid var(--line); border-radius:10px; padding:8px 12px; width:280px;}
    .search input{border:none; outline:none; font-size:13.5px; width:100%; color:var(--ink-900);}
    .search i{color:var(--ink-400); font-size:13px;}
    .filters{display:flex; gap:8px; margin-bottom:14px; flex-wrap:wrap;}
    .pill{padding:7px 14px; border-radius:999px; font-size:13px; font-weight:600; cursor:pointer; border:1px solid var(--line); background:#fff; color:var(--ink-600);}
    .pill.active{border-color:var(--blue); background:var(--blue); color:#fff;}

    /* Table */
    table{width:100%; border-collapse:collapse; min-width:640px;}
    thead th{text-align:left; font-size:12px; font-weight:700; color:var(--ink-400); text-transform:uppercase;
        letter-spacing:0.4px; padding:0 14px 12px; border-bottom:1px solid var(--line);}
    td{padding:14px; font-size:13.5px; color:var(--ink-900); border-bottom:1px solid var(--line);}
    td.muted{color:var(--ink-400);}
    td.sub{color:var(--ink-600);}
    td.bold{font-weight:600;}
    .table-wrap{overflow-x:auto;}
    .actions-cell{display:flex; gap:6px; justify-content:flex-end;}

    /* Badge */
    .badge{font-size:12.5px; font-weight:600; padding:4px 10px; border-radius:999px; white-space:nowrap; display:inline-block;}
    .badge.pending{background:var(--amber-bg); color:var(--amber);}
    .badge.approved{background:var(--green-bg); color:var(--green);}
    .badge.rejected{background:var(--red-bg); color:var(--red);}
    .badge.active{background:var(--green-bg); color:var(--green);}
    .badge.blocked{background:var(--red-bg); color:var(--red);}
    .badge.open{background:var(--amber-bg); color:var(--amber);}
    .badge.resolved{background:var(--green-bg); color:var(--green);}
    .badge.public{background:var(--green-bg); color:var(--green);}
    .badge.hidden{background:#EEF1F6; color:var(--ink-600);}
    .tag{font-size:11.5px; font-weight:700; padding:3px 8px; border-radius:999px;}
    .tag.verified{background:var(--green-bg); color:var(--green);}
    .tag.unverified{background:var(--amber-bg); color:var(--amber);}

    /* Icon buttons */
    .icon-btn{width:32px; height:32px; border-radius:8px; border:1px solid var(--line); background:#fff;
        display:grid; place-items:center; cursor:pointer; color:var(--ink-600); font-size:13px;}
    .icon-btn.green{color:var(--green);} .icon-btn.red{color:var(--red);}

    /* Company card */
    .company-card .top{display:flex; justify-content:space-between; align-items:flex-start;}
    .company-name{font-weight:700; font-size:15px; color:var(--ink-900);}
    .company-industry{font-size:12.5px; color:var(--ink-400); margin-top:2px;}
    .company-stats{display:flex; gap:18px; margin-top:16px; font-size:13px;}
    .company-stats .stat-label{color:var(--ink-400);}
    .company-stats .stat-value{font-weight:700; color:var(--ink-900);}
    .company-stats .stat-value.danger{color:var(--red);}

    canvas{max-width:100%;}
</style>



