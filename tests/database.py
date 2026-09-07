import sqlite3,pathlib,re
root=pathlib.Path(__file__).resolve().parents[1]
db=sqlite3.connect(':memory:')
db.executescript((root/'drizzle/0000_adorable_stardust.sql').read_text())
for c in ['shared','candidate','unrelated']:
 db.execute('INSERT INTO creators(id,name,platform,url,feed) VALUES(?,?,?,?,?)',(c,c,'YouTube','https://example.com/'+c,'https://example.com/'+c))
for user,creator in [('alice','shared'),('bob','shared'),('bob','candidate'),('carol','shared'),('carol','candidate'),('dave','unrelated')]:
 db.execute('INSERT INTO subscriptions VALUES(?,?,?)',(user,creator,1))
sql=re.search(r'recommendationsSQL\s*=\s*`(.*?)`',(root/'db/store.ts').read_text(),re.S).group(1)
rows=db.execute(sql,('alice','alice','alice')).fetchall()
assert len(rows)==1 and rows[0][0]=='candidate' and rows[0][-1]==2,rows
assert not db.execute(sql,('dave','dave','dave')).fetchall()
db.execute('DELETE FROM subscriptions WHERE user_id=? AND creator_id=?',('alice','candidate'))
assert db.execute('SELECT COUNT(*) FROM subscriptions WHERE creator_id=?',('candidate',)).fetchone()[0]==2
plan=db.execute('EXPLAIN QUERY PLAN SELECT * FROM subscriptions WHERE user_id=? AND creator_id=?',('alice','shared')).fetchall()
assert any('INDEX' in row[-1] for row in plan)
print('PASS: schema migration, two-peer recommendation ranking, unrelated-user exclusion, scoped removal, indexed ownership lookup')

