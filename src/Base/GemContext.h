/*-----------------------------------------------------------------
LOG
    GEM - Graphics Environment for Multimedia

    a rendering context

    Copyright (c) 2009 IOhannes m zmoelnig. forum::f�r::uml�ute. IEM. zmoelnig@iem.kug.ac.at
    For information on usage and redistribution, and for a DISCLAIMER OF ALL
    WARRANTIES, see the file, "GEM.LICENSE.TERMS" in this distribution.

-----------------------------------------------------------------*/
#ifndef INCLUDE_GEMCONTEXT_H_
#define INCLUDE_GEMCONTEXT_H_

#include "Gem/ExportDef.h"
#include "Gem/GemGL.h"


# if defined _WIN32
typedef struct WGLEWContextStruct WGLEWContext;
#  define GemGlewXContext WGLEWContext
# elif defined __linux__ || defined HAVE_GL_GLX_H
typedef struct GLXEWContextStruct GLXEWContext;
#  define GemGlewXContext GLXEWContext
# else
#  define GemGlewXContext void
# endif

typedef struct GLEWContextStruct GLEWContext;

namespace gem {
class GEM_EXTERN Context {
 private:
  class PIMPL;
  PIMPL*m_pimpl;

 public:
  Context(void);
  Context(const Context&);
  virtual ~Context(void);

  Context&operator=(const Context&);

  // make context current
  bool push(void);

  // make context uncurrent
  bool pop(void);

 public:
  static unsigned int getContextId(void);
  static GLEWContext*getGlewContext(void);
  static GemGlewXContext*getGlewXContext(void);
};

}; // namespace
#endif  // for header file