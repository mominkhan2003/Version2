create or replace function pg_temp.ev(d date, t text, ty text, mp text, st text, et text, n int, x text, rv text) returns uuid language sql as $$
  insert into public.events(event_date,title,activity_type,meeting_point,location,start_time,end_time,volunteers_needed,extra_info,status,published,needs_review,review_note,is_demo)
  values (d,t,ty,mp,case when ty='serving' then 'Uni Sonnenfelsplatz' else 'Volkshaus Graz, Lagergasse 98a' end,st,et,n,x,'open',true,rv is not null,rv,true) returning id $$;
create or replace function pg_temp.asg(e uuid, people text[][]) returns void language plpgsql as $$
declare i int; begin
  for i in 1..coalesce(array_length(people,1),0) loop
    insert into public.assignments(event_id,person_name,constraint_note) values (e, people[i][1], nullif(people[i][2],''));
  end loop; end $$;

do $$
declare e uuid;
  s1 text := 'Schicht 1 (Kochen und Aufbau)'; s2 text := 'Schicht 2 (Ausgabe, Abbau, Aufräumen)';
  k text := '8-12:30 (Kochen & Liefern) (~10 Personen)'; a text := '12:3 - 16 Uhr (Aufbau & Ausgabe) (~ 7 Personen)';
  w text := '16-18 Uhr (Abwaschen) (~ 4 Personen)'; ek text := 'Einkaufen (~ 3 Personen)';
  x1 text := 'ca 8-13 Uhr, im Volkshaus Graz, Lagergasse 98a'; x2 text := 'ca 13-16 Uhr, Uni Sonnenfelsplatz';
  r12 text := 'Originalzeit "12:3" – vermutlich 12:30';
  dt date;
begin
  e := pg_temp.ev('2026-10-06',s1,'cooking','Volkshaus','08:00','13:00',null,x1,null);
  perform pg_temp.asg(e, array[['Moja',''],['Tess','+1'],['Aaron',''],['Anna','ab 10 uhr'],['Aaron A','ab 10'],['Peter','ab 10 uhr'],['Benji','ab 10 uhr']]);
  e := pg_temp.ev('2026-10-06',s2,'serving','Sonnenfelsplatz','13:00','16:00',null,x2,'In der Vorlage folgt eine Zeile nur mit Treffpunkt "Volkshaus" ohne Datum/Aktivität');
  perform pg_temp.asg(e, array[['Moja',''],['Peter',''],['Tess',''],['Benji',''],['Hannah',''],['Anna','']]);
  e := pg_temp.ev('2026-10-07',s1,'cooking','Volkshaus','08:00','13:00',null,x1,null);
  perform pg_temp.asg(e, array[['Benji',''],['Peter',''],['Paul',''],['Eva','']]);
  e := pg_temp.ev('2026-10-07',s2,'serving',null,'13:00','16:00',null,x2,'Treffpunkt fehlt in der Vorlage');
  perform pg_temp.asg(e, array[['Hannah',''],['Benji',''],['Peter',''],['Aaron','ab 13:20'],['Daniel','ab ~13:00'],['Tess','bis 14 Uhr']]);

  e := pg_temp.ev('2026-10-14',k,'cooking','Volkshaus','08:00','12:30',10,null,null);
  perform pg_temp.asg(e, array[['Elias',''],['Hannah',''],['Moja',''],['Anna',''],['Sophie','bis 12:30'],['Alex','ab 10:00 · +43 664 5050593'],['Eva',''],['Peter',''],['Martin',''],['Nella',''],['Tess',''],['Clemens','ab 11:15']]);
  e := pg_temp.ev('2026-10-14',a,'serving','Sonnenfelsplatz','12:30','16:00',7,null,r12);
  perform pg_temp.asg(e, array[['Hannah',''],['Tess','bis 15 Uhr'],['Aaron','ab 13:20'],['Moja',''],['Clara B.',''],['Sascha',''],['Lotta','bis 15 Uhr'],['Anna Biene',''],['Jette','ab 14:30'],['Jasmin','bis 15 Uhr'],['Clemens','bis 14:15']]);
  e := pg_temp.ev('2026-10-14',w,'dishwashing','Volkshaus','16:00','18:00',4,null,null);
  perform pg_temp.asg(e, array[['Sophie','bis 17:45'],['Marijke',''],['Clara L','']]);
  e := pg_temp.ev('2026-10-13',ek,'shopping','Volkshaus',null,null,3,null,null);
  perform pg_temp.asg(e, array[['Indra',''],['Eva H',''],['Benji','']]);

  e := pg_temp.ev('2026-10-21',k,'cooking','Volkshaus','08:00','12:30',10,null,null);
  perform pg_temp.asg(e, array[['Peter',''],['Hannah',''],['Moja',''],['Anna',''],['Manu',''],['Martin','bis 12:00'],['Indra',''],['Anna Biene',''],['Sophie','bis 13:30'],['Ida','bis 12:00'],['Clemens','ab 11'],['Ranjodh','']]);
  e := pg_temp.ev('2026-10-21',a,'serving','Sonnenfelsplatz','12:30','16:00',7,null,r12);
  perform pg_temp.asg(e, array[['Marianne',''],['Peter',''],['Anna',''],['Manu',''],['Eva H',''],['moja','']]);
  e := pg_temp.ev('2026-10-21',w,'dishwashing','Volkshaus','16:00','18:00',4,null,null);
  perform pg_temp.asg(e, array[['Aaron',''],['Marijke',''],['Ben','auch']]);
  e := pg_temp.ev('2026-10-20',ek,'shopping','Volkshaus',null,null,3,null,null);
  perform pg_temp.asg(e, array[['Clara B.',''],['Marianne',''],['Susanne',''],['Alea',''],['Ben','if possible c: · Nr is +436641554358 falls nicht geht']]);

  e := pg_temp.ev('2026-10-28',k,'cooking','Volkshaus','08:00','12:30',10,null,null);
  perform pg_temp.asg(e, array[['Peter',''],['Hannah',''],['Marianne',''],['Nella',''],['Anna S.',''],['Eva H',''],['Sarah',''],['Eva',''],['Martin',''],['Kathi','bis 11:10'],['Moja',''],['Ranjodh','']]);
  e := pg_temp.ev('2026-10-28',a,'serving','Sonnenfelsplatz','12:30','16:00',7,null,r12||'; Eintrag "Emily (" in der Vorlage unvollständig');
  perform pg_temp.asg(e, array[['Marianne','bis 14 Uhr'],['Peter',''],['Emily','Original: "Emily (" – unvollständig'],['Sophie',''],['Moja',''],['Ranjodh','']]);
  perform pg_temp.ev('2026-10-28',w,'dishwashing','Volkshaus','16:00','18:00',4,null,null);
  perform pg_temp.ev('2026-10-27',ek,'shopping','Volkshaus',null,null,3,null,null);

  foreach dt in array array['2026-11-04','2026-11-11','2026-11-18','2026-11-25']::date[] loop
    perform pg_temp.ev(dt,k,'cooking','Volkshaus','08:00','12:30',10,null,null);
    perform pg_temp.ev(dt,a,'serving','Sonnenfelsplatz','12:30','16:00',7,null,r12);
    perform pg_temp.ev(dt,w,'dishwashing','Volkshaus','16:00','18:00',4,null,null);
    perform pg_temp.ev(dt - 1,ek,'shopping','Volkshaus',null,null,3,null,null);
  end loop;
end $$;

insert into public.donation_requests(full_name,email,donation_type,amount,message,consent,status,is_demo) values
('DEMO Beispiel Person','demo@example.org','money',50,'Demo-Datensatz',true,'new',true),
('DEMO Lebensmittelspende','demo2@example.org','food',null,'Demo: 20 kg Kartoffeln',true,'contacted',true);
insert into public.posts(title,caption,template,format) values ('DEMO Mitmachtreffen','Komm zum Mensa Mitmachtreffen!','event','portrait');