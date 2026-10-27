import json,html
e=lambda s: html.escape(s,quote=False)
def build_ask(rows, cycle):
    """rows: dicts stem,c,w,d,ask,src,steps,reasons,concept,sec,order"""
    out=[]
    for i,r in enumerate(rows):
        k=cycle[i%len(cycle)]; opts=list(r['w']); opts.insert(k,r['c'])
        letters='abcd'; wr=[]; wi=0
        for j in range(4):
            if j==k: continue
            wr.append((letters[j],opts[j],r['reasons'][wi])); wi+=1
        steps=[f"Rule: {r['src']}"]+list(r['steps'])
        exp=("<p><strong>What the question is asking:</strong> %s</p>"%e(r['ask'])
             +"<p><strong>Step by step:</strong><br>"+"<br>".join("%d. %s"%(n+1,e(s)) for n,s in enumerate(steps))+"</p>"
             +"<p><strong>Why the other options are wrong:</strong><br>"+"<br>".join("(%s) %s: %s"%(l,e(o),e(rs)) for l,o,rs in wr)+"</p>"
             +"<p><strong>Concept behind the question:</strong> %s Revise Section %s.</p>"%(e(r['concept']),r['sec']))
        out.append({"body":"<p>%s</p>"%e(r['stem']),"option_1":e(opts[0]),"option_2":e(opts[1]),"option_3":e(opts[2]),"option_4":e(opts[3]),"correct":k+1,"explanation":exp,"difficulty":r['d'],"order":r['order']})
    return out
