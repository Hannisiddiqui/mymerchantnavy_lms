import json,html,re,collections
e=lambda s: html.escape(s,quote=False)
def build(rows, cycle):
    """rows: dicts with stem,c,w,d,rule,work,check,reasons,concept,sec(str),step; cycle: list of correct positions (0-3)"""
    out=[]
    for i,r in enumerate(rows):
        k=cycle[i%len(cycle)]; opts=list(r['w']); opts.insert(k,r['c'])
        letters='abcd'; wr=[]; wi=0
        for j in range(4):
            if j==k: continue
            wr.append((letters[j],opts[j],r['reasons'][wi])); wi+=1
        exp=("<p><strong>Rule:</strong> %s</p>"%e(r['rule'])
             +"<p><strong>Working:</strong> "+"<br>".join("%d. %s"%(n+1,e(w)) for n,w in enumerate(r['work']))+"</p>"
             +"<p><strong>Result:</strong> %s</p>"%e(r['result'])
             +"<p><strong>Check:</strong> %s</p>"%e(r['check'])
             +"<p><strong>Why the tempting options fail:</strong><br>"+"<br>".join("• (%s) %s"%(l,e(rs)) for l,o,rs in wr)+"</p>"
             +"<p><strong>Concept behind the question:</strong> %s</p>"%e(r['concept']))
        out.append({"body":"<p>%s</p>"%e(r['stem']),"option_1":e(opts[0]),"option_2":e(opts[1]),"option_3":e(opts[2]),"option_4":e(opts[3]),"correct":k+1,"explanation":exp,"difficulty":r['d'],"order":r['order']})
    return out
